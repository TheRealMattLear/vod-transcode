<?php

namespace App\Jobs;

use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class TranscodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $file;
    protected string $output;
    protected string|null $notify;
    protected int|null $bitrate;

    public function __construct($file,$output,$bitrate,$notify)
    {
        $this->file = $file;
        $this->output = $output;
        $this->bitrate = $bitrate;
        $this->notify = $notify;
    }

    public function handle()
    {
        Log::info("Downloading file from s3://{$this->file}");
        Storage::writeStream($this->file, Storage::cloud()->readStream("/tmp/{$this->file}"));
        Log::info('File downloaded successfully, processing...');

        $file = FFMpeg::open($this->file);

        # If bitrate >= specified, reduce down; else use original
        $bitrateOptimal = (int) intval($file->getVideoStream()->get('bit_rate')) / 1000;
        if ( $bitrateOptimal > $this->bitrate ) $bitrateOptimal = $this->bitrate;
        $bitrateMin = (int) ($bitrateOptimal * 0.85);
        $bitrateMax = (int) ($bitrateOptimal * 1.25);
        $buffSize = 2000;

        $bitrateFormat = (new X264)
            ->setAdditionalParameters([
                "-minrate", "{$bitrateMin}K",
                "-maxrate", "{$bitrateMax}K",
                "-bufsize", "{$buffSize}K",
                "-preset", "ultrafast",
            ])
            ->setKiloBitrate(0) # Set bitrate to 0 to disable constant bitrate and use vbr for faster process
            ->setPasses(1); # setPasses to process fast and not concern ourselves much with accurate bitrate

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

        if ( !empty($this->notify) )
            Log::info('POST notification to ' . $this->notify);
            Http::timeout(10)->post($this->notify,[
                'width'    => $videoDimensions->getWidth(),
                'height'   => $videoDimensions->getHeight(),
                'duration' => $file->getDurationInSeconds(),
                'size'     => Storage::size($tmpName),
            ]);
    }
    public function fail($exception = null)
    {
        Storage::delete($this->file); // Cleanup local file
    }
}
