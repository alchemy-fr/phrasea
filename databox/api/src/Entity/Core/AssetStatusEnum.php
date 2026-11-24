<?php

namespace App\Entity\Core;

enum AssetStatusEnum: int
{
    // Accepted/Published
    case Accepted = 0;

    // Pending for analysis
    case Pending = 1;

    case Quarantined = 2;

    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->name] = $case;
        }

        return $choices;
    }
}
