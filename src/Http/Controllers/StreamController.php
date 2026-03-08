<?php

namespace Shamimstack\Tgsdk\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Shamimstack\Tgsdk\ChunkManager;
use Shamimstack\Tgsdk\Models\TelegramFile;

class StreamController extends Controller
{
    public function __construct(
        protected ChunkManager $chunkManager,
    ) {}

    /**
     * Stream a file to the client by download token.
     */
    public function stream(Request $request, string $token): StreamedResponse|Response
    {
        $file = TelegramFile::where('download_token', $token)->first();

        if (! $file) {
            abort(404, 'File not found.');
        }

        if (! $file->isAvailable()) {
            if ($file->isFailed()) {
                abort(410, 'File is no longer available.');
            }
            abort(202, 'File is still being processed.');
        }

        $headers = [
            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . ($file->original_name ?? basename($file->disk_path)) . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ];

        if (! $file->is_chunked) {
            return $this->streamSingleFile($file, $request, $headers);
        }

        return $this->streamChunkedFile($file, $request, $headers);
    }

    /**
     * Stream a single (non-chunked) file.
     */
    protected function streamSingleFile(TelegramFile $file, Request $request, array $headers): StreamedResponse|Response
    {
        $headers['Content-Length'] = $file->size;

        // The actual content retrieval from Telegram happens here.
        // In production, this would use Pyrogram or Bot API to fetch the file.
        // For now, we provide the streaming infrastructure.
        return new StreamedResponse(function () use ($file) {
            $this->outputTelegramFile($file->file_id, $file->channel_id);
        }, 200, $headers);
    }

    /**
     * Stream a chunked file by sequentially streaming each chunk.
     */
    protected function streamChunkedFile(TelegramFile $file, Request $request, array $headers): StreamedResponse
    {
        $headers['Content-Length'] = $file->size;

        $chunks = $file->chunks()->where('status', 'available')->orderBy('chunk_index')->get();

        return new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                $content = $this->fetchTelegramFileContent($chunk->file_id_tg, $chunk->channel_id);

                // Decrypt if needed
                if ($chunk->encryption_iv) {
                    $content = $this->chunkManager->decryptContent($content);
                }

                // Decompress if needed
                if ($chunk->is_compressed) {
                    $content = $this->chunkManager->decompressContent($content);
                }

                echo $content;
                flush();
            }
        }, 200, $headers);
    }

    /**
     * Output a Telegram file to the response stream.
     * This is the integration point with Telegram's download API.
     */
    protected function outputTelegramFile(?string $fileId, string $channelId): void
    {
        if (! $fileId) {
            return;
        }

        // In production, this would call the Telegram Bot API:
        // GET https://api.telegram.org/file/bot<token>/<file_path>
        // or use a local Pyrogram download endpoint.
        //
        // For the package, we provide a configurable download mechanism.
        // The actual implementation depends on the deployment:
        // - Bot API: HTTP GET to Telegram servers
        // - Local Bot API Server: HTTP GET to local server
        // - Pyrogram download proxy: HTTP GET to Python worker download endpoint

        $botToken = config('telegram-storage.pyrogram.bot_token');

        if ($botToken) {
            // Use Bot API to get file path, then stream it
            $apiUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
            $response = json_decode(file_get_contents($apiUrl), true);

            if (isset($response['result']['file_path'])) {
                $filePath = $response['result']['file_path'];
                $downloadUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

                $stream = fopen($downloadUrl, 'rb');
                if ($stream) {
                    while (! feof($stream)) {
                        echo fread($stream, 8192);
                        flush();
                    }
                    fclose($stream);
                }
            }
        }
    }

    /**
     * Fetch the full content of a Telegram file (for chunked reassembly).
     */
    protected function fetchTelegramFileContent(?string $fileId, string $channelId): string
    {
        if (! $fileId) {
            return '';
        }

        $botToken = config('telegram-storage.pyrogram.bot_token');

        if (! $botToken) {
            return '';
        }

        $apiUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
        $response = json_decode(file_get_contents($apiUrl), true);

        if (isset($response['result']['file_path'])) {
            $filePath = $response['result']['file_path'];
            $downloadUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";

            $content = file_get_contents($downloadUrl);

            return $content !== false ? $content : '';
        }

        return '';
    }
}
