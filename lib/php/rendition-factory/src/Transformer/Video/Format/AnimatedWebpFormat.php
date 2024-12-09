<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

class AnimatedWebpFormat implements FormatInterface
{
    public static function getAllowedExtensions(): array
    {
        return ['webp'];
    }

    public static function getMimeType(): string
    {
        return 'image/webp';
    }

    public static function getFormat(): string
    {
        return 'animated-webp';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Animation;
    }
}
