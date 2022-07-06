<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('/process', [\App\Http\Controllers\TranscodeController::class,'index']);
Route::get('/reprocess', [\App\Http\Controllers\TranscodeController::class,'reprocess']);
Route::get('/validate', [\App\Http\Controllers\TranscodeController::class,'validate']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
