<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;

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
