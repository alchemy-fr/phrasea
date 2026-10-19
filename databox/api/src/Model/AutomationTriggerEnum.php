<?php

declare(strict_types=1);

namespace App\Model;

enum AutomationTriggerEnum: string
{
    case Manual = 'manual';
    case Schedule = 'schedule';

    public static function getChoices(): array
    {
        return [
            'Manual' => self::Manual,
            'Schedule' => self::Schedule,
        ];
    }
}
