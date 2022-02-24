<?php

namespace App\Jobs;

use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class TranscodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $file;

    public function __construct($file,$notify)
    {
        $this->file = $file;
        $this->notify = $notify;
    }

    public function handle()
    {
        Log::info('Downloading file');
        $file = Storage::put($this->file, Storage::cloud()->get("/tmp/{$this->file}"));
        Log::info('File downloaded successfully, processing...');

        $file = FFMpeg::open($this->file);
        $bitRate = $file->getVideoStream()->get('bit_rate');
        $bitRate = (int) ($bitRate / 1000);
        $bitrateFormat = (new X264)->setKiloBitrate($bitRate);
        $videoDimensions = $file->getVideoStream()->getDimensions();

        $file->export()
            ->inFormat($bitrateFormat)
            ->toDisk('s3')
            ->save("processed/{$this->file}");

        Log::info('Processing completed');

        Http::get($this->notify);
    }
}
