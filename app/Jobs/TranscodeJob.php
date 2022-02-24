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
    protected string $notify;

    public function __construct($file,$output,$notify)
    {
        $this->file = $file;
        $this->output = $output;
        $this->notify = $notify;
    }

    public function handle()
    {
        Log::info('Downloading file');
        Storage::writeStream($this->file, Storage::cloud()->readStream("/tmp/{$this->file}"));
        Log::info('File downloaded successfully, processing...');

        $file = FFMpeg::open($this->file);
        $bitRate = $file->getVideoStream()->get('bit_rate');
        $bitRate = (int) ($bitRate / 1000);
        $bitrateFormat = (new X264)->setKiloBitrate($bitRate);
        $videoDimensions = $file->getVideoStream()->getDimensions();

        $file->export()
            ->inFormat($bitrateFormat)
            ->toDisk('s3')
            ->save($this->output);

        Log::info('Processing completed');

        Storage::delete($this->file); // Cleanup local file
        //Storage::cloud()->delete($this->file); // Cleanup unprocessed file (we'll let mediacp cloud do this for now)

        Http::get($this->notify);
    }
}
