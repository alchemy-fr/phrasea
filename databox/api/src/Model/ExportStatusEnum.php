<?php

declare(strict_types=1);

namespace App\Model;

enum ExportStatusEnum: int
{
    case Pending = 0;
    case InProgress = 1;
    case Ready = 2;

    public static function getChoices(): array
    {
        return [
            'Pending' => self::Pending,
            'InProgress' => self::InProgress,
            'Ready' => self::Ready,
        ];
    }
}
