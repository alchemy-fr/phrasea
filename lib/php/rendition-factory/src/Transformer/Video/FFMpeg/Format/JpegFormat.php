<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;

class JpegFormat implements FormatInterface
{
    public static function getAllowedExtensions(): array
    {
        return ['jpg', 'jpeg'];
    }

    public static function getMimeType(): string
    {
        return 'image/jpeg';
    }

    public static function getFormat(): string
    {
        return 'image-jpeg';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
