<?php

declare(strict_types=1);

namespace App\Model;

enum SavedSearchPrivacyEnum: int
{
    // Only the owner and granted users can see and access the search
    case Secret = 0;

    // Accessible by direct link but not listed to other users
    case Private = 1;

    // Listed to every user
    case Public = 2;

    public static function getChoices(): array
    {
        return [
            'Secret' => self::Secret,
            'Private' => self::Private,
            'Public' => self::Public,
        ];
    }

    /**
     * @return int[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case): int => $case->value, self::cases());
    }
}
