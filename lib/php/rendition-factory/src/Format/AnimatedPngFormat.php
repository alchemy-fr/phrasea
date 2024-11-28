<?php

namespace Alchemy\RenditionFactory\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

class AnimatedPngFormat implements FormatInterface
{
    public static function getAllowedExtensions(): array
    {
        return ['apng', 'png'];
    }

    public static function getMimeType(): string
    {
        return 'image/apng';
    }

    public static function getFormat(): string
    {
        return 'animated-png';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Animation;
    }
}
