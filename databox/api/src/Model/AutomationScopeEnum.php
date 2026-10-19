<?php

declare(strict_types=1);

namespace App\Model;

enum AutomationScopeEnum: string
{
    // Only the results not handled by a previous run
    case New = 'new';

    // Every result, at each run
    case All = 'all';

    public static function getChoices(): array
    {
        return [
            'New results' => self::New,
            'All results' => self::All,
        ];
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
