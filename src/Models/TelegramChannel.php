<?php

namespace Shamimstack\Tgsdk\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramChannel extends Model
{
    protected $table = 'telegram_channels';

    protected $fillable = [
        'channel_identifier',
        'label',
        'is_active',
        'priority',
        'total_files',
        'total_bytes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'total_files' => 'integer',
            'total_bytes' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incrementFileCount(int $bytes): void
    {
        $this->increment('total_files');
        $this->increment('total_bytes', $bytes);
    }

    public function decrementFileCount(int $bytes): void
    {
        $this->decrement('total_files');
        $this->decrement('total_bytes', $bytes);
    }
}
