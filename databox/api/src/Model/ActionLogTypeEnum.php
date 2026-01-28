<?php

namespace App\Model;

enum ActionLogTypeEnum: int
{
    case AssetMoved = 1;
    case AssetAddedToCollection = 2;
    case AssetRemovedFromCollection = 3;
    case AssetSubstituted = 4;
    case AssetAttributeChanged = 5;

    public static function getChoices(): array
    {
        return [
            'Asset Moved' => self::AssetMoved,
            'Asset Added To Collection' => self::AssetAddedToCollection,
            'Asset Removed From Collection' => self::AssetRemovedFromCollection,
            'Asset Substituted' => self::AssetSubstituted,
            'Asset Attribute Changed' => self::AssetAttributeChanged,
        ];
    }
}
