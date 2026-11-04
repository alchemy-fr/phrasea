<?php

declare(strict_types=1);

namespace App\Model;

enum AssetImportStatusEnum: int
{
    case Pending = 0;
    /** The instructions file is being read: the total is not known yet */
    case Dispatching = 1;
    /** Every entry is dispatched: waiting for the entries to be processed */
    case Processing = 2;
    case Completed = 3;
    case Failed = 4;

    public function getLabel(): string
    {
        return strtolower($this->name);
    }

    public function isRunning(): bool
    {
        return match ($this) {
            self::Pending, self::Dispatching, self::Processing => true,
            default => false,
        };
    }
}
