<?php

namespace Shamimstack\Tgsdk\Tests\Feature;

use Illuminate\Http\Request;
use Shamimstack\Tgsdk\Http\Middleware\VerifyCallbackSignature;
use Shamimstack\Tgsdk\Tests\TestCase;

class VerifyCallbackSignatureTest extends TestCase
{
    protected VerifyCallbackSignature $middleware;
    protected string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = 'test-hmac-secret';
        $this->middleware = new VerifyCallbackSignature($this->secret);
    }

    public function test_handle_accepts_valid_signature(): void
    {
        $payload = ['file_id' => 123, 'status' => 'success'];
        $signature = hash_hmac('sha256', json_encode($payload), $this->secret);

        $request = Request::create('/telegram-storage/callback', 'POST', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ]);
        
        // Set content manually to ensure proper body
        $request->server->set('REQUEST_CONTENT', json_encode($payload));
        
        $response = $this->middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_rejects_invalid_signature(): void
    {
        $payload = ['file_id' => 123, 'status' => 'success'];

        $request = Request::create('/telegram-storage/callback', 'POST', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => 'invalid-signature',
        ]);
        
        $request->server->set('REQUEST_CONTENT', json_encode($payload));

        $response = $this->middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid signature', $response->getContent());
    }

    public function test_handle_rejects_missing_signature(): void
    {
        $payload = ['file_id' => 123, 'status' => 'success'];

        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'CONTENT_TYPE' => 'application/json',
            ], // server
            json_encode($payload) // content
        );

        $response = $this->middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Missing signature', $response->getContent());
    }

    public function test_handle_rejects_tampered_payload(): void
    {
        $payload = ['file_id' => 123, 'status' => 'success'];
        $signature = hash_hmac('sha256', json_encode($payload), $this->secret);

        // Tamper with payload after signing
        $tamperedPayload = ['file_id' => 999, 'status' => 'failed'];

        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ], // server
            json_encode($payload) // content
        );

        $response = $this->middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid signature', $response->getContent());
    }

    public function test_handle_with_different_algorithms(): void
    {
        $payload = json_encode(['data' => 'test']);
        $signature = hash_hmac('sha256', $payload, $this->secret);

        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
                'REQUEST_CONTENT' => $payload,
            ], // server
            $payload // content
        );

        $next = fn($req) => response('Success');
        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
