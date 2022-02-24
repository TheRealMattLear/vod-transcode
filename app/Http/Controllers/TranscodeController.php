<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeJob;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class TranscodeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|string',
            'notify' => 'required|string|url',
            'output' => 'required|string'
        ]);

        # TODO: Validate path exists or throw error

        TranscodeJob::dispatch($request->input('file'), $request->input('notify'));

        return response()->json(['ok']);
    }
}
