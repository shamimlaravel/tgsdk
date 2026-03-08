<?php

namespace Shamimstack\Tgsdk;

use Illuminate\Support\Facades\Redis;
use Shamimstack\Tgsdk\Models\TelegramChannel;

class ChannelRotator
{
    protected string $strategy;

    protected string $redisConnection;

    public function __construct()
    {
        $this->strategy = config('telegram-storage.rotation_strategy', 'round-robin');
        $this->redisConnection = config('telegram-storage.redis.connection', 'default');
    }

    public function select(): TelegramChannel
    {
        return match ($this->strategy) {
            'least-used' => $this->leastUsed(),
            'capacity-aware' => $this->capacityAware(),
            default => $this->roundRobin(),
        };
    }

    protected function roundRobin(): TelegramChannel
    {
        $channels = TelegramChannel::active()->orderBy('priority')->orderBy('id')->get();

        if ($channels->isEmpty()) {
            throw new \RuntimeException('No active Telegram channels configured.');
        }

        $counter = Redis::connection($this->redisConnection)->incr('telegram_storage:rr_counter');
        $index = ($counter - 1) % $channels->count();

        return $channels[$index];
    }

    protected function leastUsed(): TelegramChannel
    {
        $channel = TelegramChannel::active()
            ->orderBy('total_files')
            ->orderBy('priority')
            ->first();

        if (! $channel) {
            throw new \RuntimeException('No active Telegram channels configured.');
        }

        return $channel;
    }

    protected function capacityAware(): TelegramChannel
    {
        $channel = TelegramChannel::active()
            ->orderBy('total_bytes')
            ->orderBy('priority')
            ->first();

        if (! $channel) {
            throw new \RuntimeException('No active Telegram channels configured.');
        }

        return $channel;
    }
}
