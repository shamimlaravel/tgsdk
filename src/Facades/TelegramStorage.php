<?php

namespace Shamimstack\Tgsdk\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Shamimstack\Tgsdk\TelegramStorageAdapter
 */
class TelegramStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'telegram-storage';
    }
}
