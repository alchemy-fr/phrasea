<?php

namespace App\Model;

enum AssetTypeEnum: int
{
    case Asset = 1;
    case Story = 2;
    case Both = 3;

    public static function values(): array
    {
        return [
            self::Asset->value,
            self::Story->value,
            self::Both->value,
        ];
    }
}
