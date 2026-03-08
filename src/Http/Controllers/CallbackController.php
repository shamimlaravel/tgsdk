<?php

namespace Shamimstack\Tgsdk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Shamimstack\Tgsdk\Events\TelegramChunkCompleted;
use Shamimstack\Tgsdk\Events\TelegramChunkFailed;
use Shamimstack\Tgsdk\Events\TelegramUploadCompleted;
use Shamimstack\Tgsdk\Events\TelegramUploadFailed;
use Shamimstack\Tgsdk\Models\TelegramChannel;
use Shamimstack\Tgsdk\Models\TelegramFile;
use Shamimstack\Tgsdk\Models\TelegramFileChunk;

class CallbackController extends Controller
{
    /**
     * Handle upload completion callback from the Python worker.
     */
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_record_id' => 'required|string',
            'chunk_index' => 'nullable|integer',
            'status' => 'required|in:success,failed',
            'message_id' => 'nullable|integer',
            'file_id' => 'nullable|string',
            'file_unique_id' => 'nullable|string',
            'error' => 'nullable|string',
            'session_name' => 'nullable|string',
        ]);

        $file = TelegramFile::find($validated['file_record_id']);

        if (! $file) {
            return response()->json(['error' => 'File record not found.'], 404);
        }

        if ($validated['status'] === 'failed') {
            return $this->handleFailure($file, $validated);
        }

        return $this->handleSuccess($file, $validated);
    }

    /**
     * Process a successful upload callback.
     */
    protected function handleSuccess(TelegramFile $file, array $data): JsonResponse
    {
        if ($data['chunk_index'] !== null) {
            // Update the specific chunk
            $chunk = TelegramFileChunk::where('file_id', $file->id)
                ->where('chunk_index', $data['chunk_index'])
                ->first();

            if ($chunk) {
                $chunk->update([
                    'message_id' => $data['message_id'],
                    'file_id_tg' => $data['file_id'],
                    'session_name' => $data['session_name'] ?? null,
                    'status' => 'available',
                ]);

                TelegramChunkCompleted::dispatch($file->id, $data['chunk_index'], $data['file_id']);
            }

            // Check if all chunks are now available
            $totalAvailable = $file->chunks()->where('status', 'available')->count();
            if ($totalAvailable >= $file->chunk_count) {
                $file->markAsAvailable();
                $this->updateChannelCounters($file);

                TelegramUploadCompleted::dispatch($file->id, $file->disk_path, $data['file_id']);
            }
        } else {
            // Single file upload
            $file->update([
                'message_id' => $data['message_id'],
                'file_id' => $data['file_id'],
                'file_unique_id' => $data['file_unique_id'] ?? null,
                'status' => 'available',
            ]);

            $this->updateChannelCounters($file);

            TelegramUploadCompleted::dispatch($file->id, $file->disk_path, $data['file_id']);
        }

        // Clean up temp file if the path exists in the request
        $this->cleanupTempFile($data);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process a failed upload callback.
     */
    protected function handleFailure(TelegramFile $file, array $data): JsonResponse
    {
        $error = $data['error'] ?? 'Unknown error';

        if ($data['chunk_index'] !== null) {
            $chunk = TelegramFileChunk::where('file_id', $file->id)
                ->where('chunk_index', $data['chunk_index'])
                ->first();

            if ($chunk) {
                $chunk->update([
                    'status' => 'failed',
                    'last_error' => $error,
                ]);
                $chunk->incrementAttempts();

                TelegramChunkFailed::dispatch($file->id, $data['chunk_index'], $error);
            }

            // If all chunks are in terminal state, mark the file as failed
            $pendingOrUploading = $file->chunks()
                ->whereIn('status', ['pending', 'uploading'])
                ->count();

            if ($pendingOrUploading === 0) {
                $allAvailable = $file->chunks()->where('status', 'available')->count();
                if ($allAvailable < $file->chunk_count) {
                    $file->markAsFailed();
                    TelegramUploadFailed::dispatch($file->id, $file->disk_path, $error);
                }
            }
        } else {
            $file->markAsFailed();
            TelegramUploadFailed::dispatch($file->id, $file->disk_path, $error);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Update the channel's file/byte counters.
     */
    protected function updateChannelCounters(TelegramFile $file): void
    {
        $channel = TelegramChannel::where('channel_identifier', $file->channel_id)->first();

        if ($channel) {
            $channel->incrementFileCount($file->size);
        }
    }

    /**
     * Clean up temporary files after upload.
     */
    protected function cleanupTempFile(array $data): void
    {
        if (isset($data['temp_path']) && file_exists($data['temp_path'])) {
            unlink($data['temp_path']);
        }
    }
}
