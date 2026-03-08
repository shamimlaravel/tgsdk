<?php

namespace Shamimstack\Tgsdk\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramFileChunk extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'telegram_file_chunks';

    protected $fillable = [
        'file_id',
        'chunk_index',
        'channel_id',
        'session_name',
        'message_id',
        'file_id_tg',
        'size',
        'checksum',
        'is_compressed',
        'encryption_iv',
        'status',
        'attempts',
        'last_error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'message_id' => 'integer',
            'size' => 'integer',
            'is_compressed' => 'boolean',
            'attempts' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(TelegramFile::class, 'file_id');
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

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }
}
