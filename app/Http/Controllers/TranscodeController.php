<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TranscodeController extends Controller
{
    public function index(Request $request)
    {
        $file = $request->input('file');
        $validated = $request->validate([
            'file' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use($file){
                    if ( !Storage::cloud()->exists("/tmp/{$file}") ){
                        $fail("File s3://tmp/{$file} does not exist");
                    }
                }
                ],
            'output' => 'required|string',
            'bitrate' => 'int',
            'notify' => 'string|url',
            'transcode' => 'boolean'
        ]);


        TranscodeJob::dispatch(
            $request->input('file'),
            $request->input('output'),
            $request->input('bitrate'),
            $request->input('notify')
        );

        return response()->json(['ok']);
    }
}
