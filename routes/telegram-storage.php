<?php

use Illuminate\Support\Facades\Route;
use Shamimstack\Tgsdk\Http\Controllers\CallbackController;
use Shamimstack\Tgsdk\Http\Controllers\StreamController;
use Shamimstack\Tgsdk\Http\Middleware\VerifyCallbackSignature;
use Shamimstack\Tgsdk\Http\Middleware\VerifyDownloadSignature;

/*
|--------------------------------------------------------------------------
| Telegram Storage Routes
|--------------------------------------------------------------------------
|
| Streaming proxy and worker callback endpoints.
|
*/

$routePrefix = config('telegram-storage.download.route_prefix', 'tg-stream');
$middleware = config('telegram-storage.download.middleware', ['web']);

// Streaming / Download routes
Route::middleware(array_merge($middleware, [VerifyDownloadSignature::class]))
    ->prefix($routePrefix)
    ->group(function () {
        Route::get('/{token}', [StreamController::class, 'stream'])
            ->name('telegram-storage.stream')
            ->where('token', '[A-Za-z0-9]+');
    });

// Callback route from Python worker
Route::middleware([VerifyCallbackSignature::class])
    ->post('/telegram-storage/callback', [CallbackController::class, 'handle'])
    ->name('telegram-storage.callback');
