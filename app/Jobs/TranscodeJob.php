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
        $file = Storage::put($this->file, Storage::cloud()->get('07fe2889-2454-4dbb-9032-6d01bf137071/8ec1a0c8-8906-407d-9316-cbd92af85590/media/wZQRzhoFn5DS1yfRrTq1VWNuM3EKohKe9mznhb4I.mp4'));
        Log::info('File downloaded successfully, processing...');

        $file = FFMpeg::open($this->file);
        $bitRate = $file->getVideoStream()->get('bit_rate');
        $bitRate = (int) ($bitRate / 1000);
        $bitrateFormat = (new X264)->setKiloBitrate($bitRate);
        $videoDimensions = $file->getVideoStream()->getDimensions();

        $file->export()
            ->inFormat($bitrateFormat)
            ->toDisk('cloud')
            ->save("processed/{$this->file}");

        Log::info('Processing completed');

        \HttpRequest::get($this->notify);
    }
}
