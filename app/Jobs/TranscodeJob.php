<?php

namespace App\Jobs;

use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\CopyVideoFormat;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class TranscodeJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 3600;
    public $uniqueFor = 3600;

    protected string $file;
    protected string $output;
    protected int|null $bitrate;
    protected string|null $notify;
    protected bool $transcode;

    public function __construct($file, $output, $bitrate, $notify, $transcode)
    {
        $this->file = $file;
        $this->output = $output;
        $this->bitrate = $bitrate;
        $this->notify = $notify;
        $this->transcode = $transcode;
    }

    public function uniqueId()
    {
        return $this->file;
    }

    public function middleware()
    {
        # immediately delete any overlapping jobs so that they will not be retried
        # because it is expected for the ffmepg process to take a really long time
        return [(new WithoutOverlapping($this->file))->dontRelease()];
    }

    public function handle()
    {
        Log::info("Downloading file from s3://{$this->file}");
        Storage::writeStream($this->file, Storage::cloud()->readStream("/tmp/{$this->file}"));
        Log::info('File downloaded successfully, processing...');

        $file = FFMpeg::open($this->file);

        if ($this->transcode) {        # If bitrate >= specified, reduce down; else use original
            $bitrateOptimal = intval($file->getVideoStream()->get('bit_rate')) / 1000;
            if ($bitrateOptimal == 0 || $bitrateOptimal > $this->bitrate) {
                $bitrateOptimal = $this->bitrate;
            }

            $bitrateMax = (int)($this->bitrate * 1.25); # Allow burst of maximum 25% above input bitrate, doesnt mean it will actually be used
            $buffSize = $bitrateOptimal; # Target bitrate
            $bitrateFormat = (new X264)
                ->setKiloBitrate(0) # Set bitrate to 0 to disable constant bitrate and use vbr for faster process
                ->setPasses(1) # setPasses to process fast and not concern ourselves much with accurate bitrate
                ->setAdditionalParameters([
                    "-maxrate",
                    "{$bitrateMax}K",
                    "-bufsize",
                    "{$buffSize}K",
                    "-preset",
                    "ultrafast",
                    "-g",
                    "60",
                    "-keyint_min",
                    "2"
                ]);
        } else {
            $bitrateFormat = new CopyVideoFormat;
        }

        # Process to disk locally so that we can get file statistics to send back to notify url
        $tmpName = Str::random(40) . '.mp4';
        $file->export()->inFormat($bitrateFormat)->save($tmpName);
        Log::info('Processing completed');

        $transcodedFile = FFMpeg::open($tmpName);
        $videoDimensions = $transcodedFile->getVideoStream()->getDimensions();

        Log::info("Uploading processed file to s3://{$this->output}");
        Storage::cloud()->writeStream($this->output, Storage::readStream($tmpName));
        Log::info('Uploading complete');

        Storage::delete($this->file); // Cleanup local file to avoid disk build up of temp files
        Storage::cloud()->delete("/tmp/{$this->file}"); // Cleanup original tmp uploaded file

        if (!empty($this->notify)) {
            Log::info('POST notification to ' . $this->notify);
            $response = Http::timeout(10)->post($this->notify, [
                'width'    => $videoDimensions->getWidth(),
                'height'   => $videoDimensions->getHeight(),
                'duration' => $file->getDurationInSeconds(),
                'size'     => Storage::size($tmpName),
                'bitrate'  => $this->getBitrate($this->file),
            ])->throw();
            Log::info("POST response code {$response->status()} : {$response->body()}");
        }

        Log::info("TranscodeJob Completed.");
    }

    public function fail($exception = null)
    {
        Storage::delete($this->file); // Cleanup local file
        Log::error("TranscodeJob Failed." . ($exception ? $exception->getMessage() : ''));
    }

    public function getBitrate($file): bool|string
    {
        Log::info($file);
        Log::info(storage_path('app/check_bitrate.sh') . ' '. $file);
        return system(storage_path('app/check_bitrate.sh') . ' '. $file);
    }
}
