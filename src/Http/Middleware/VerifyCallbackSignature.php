<?php

namespace Shamimstack\Tgsdk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('telegram-storage.worker_callback_secret');

        if (! $secret) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');

        if (! $signature) {
            return response()->json(['error' => 'Missing signature.'], 403);
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 403);
        }

        return $next($request);
    }
}
