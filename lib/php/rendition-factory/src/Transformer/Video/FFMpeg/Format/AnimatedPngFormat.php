<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;

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
