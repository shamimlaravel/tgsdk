<?php

namespace Shamimstack\Tgsdk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDownloadSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('telegram-storage.download.signed_urls', false)) {
            return $next($request);
        }

        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link.');
        }

        return $next($request);
    }
}
