<?php

namespace Alchemy\RenditionFactory\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

class AnimatedGifFormat implements FormatInterface
{
    public static function getAllowedExtensions(): array
    {
        return ['gif'];
    }

    public static function getMimeType(): string
    {
        return 'image/gif';
    }

    public static function getFormat(): string
    {
        return 'animated-gif';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Animation;
    }
}
