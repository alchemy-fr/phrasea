<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;

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
