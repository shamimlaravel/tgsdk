<?php

namespace Shamimstack\Tgsdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramFile extends Model
{
    use HasUlids;

    protected $table = 'telegram_files';

    protected $fillable = [
        'disk_path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'status',
        'channel_id',
        'message_id',
        'file_id',
        'file_unique_id',
        'is_chunked',
        'chunk_count',
        'download_token',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'message_id' => 'integer',
            'is_chunked' => 'boolean',
            'chunk_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(TelegramFileChunk::class, 'file_id')->orderBy('chunk_index');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isUploading(): bool
    {
        return $this->status === 'uploading';
    }

    public function markAsUploading(): void
    {
        $this->update(['status' => 'uploading']);
    }

    public function markAsAvailable(): void
    {
        $this->update(['status' => 'available']);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
