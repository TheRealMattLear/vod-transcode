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
        Log::info('Downloading file');
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

        $file->export()
            ->inFormat($bitrateFormat)
            ->toDisk('s3')
            ->save($this->output);

        Log::info('Processing completed');

        Storage::delete($this->file); // Cleanup local file
        //Storage::cloud()->delete($this->file); // Cleanup unprocessed file (we'll let mediacp cloud do this for now)

        if ( !empty($this->notify) ) Http::timeout(10)->get($this->notify);
    }
    public function fail($exception = null)
    {
        Storage::delete($this->file); // Cleanup local file
    }
}
