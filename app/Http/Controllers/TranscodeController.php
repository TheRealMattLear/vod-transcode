<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeJob;
use Illuminate\Http\Request;

class TranscodeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|string',
            'notify' => 'string|url',
            'bitrate' => 'int',
            'output' => 'required|string'
        ]);

        # TODO: Validate path exists or throw error

        TranscodeJob::dispatch(
            $request->input('file'),
            $request->input('output'),
            $request->input('bitrate'),
            $request->input('notify')
        );

        return response()->json(['ok']);
    }
}
