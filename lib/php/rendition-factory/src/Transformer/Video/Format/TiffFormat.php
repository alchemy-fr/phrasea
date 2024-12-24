<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

class TiffFormat implements FormatInterface
{
    public static function getAllowedExtensions(): array
    {
        return ['tif', 'tiff'];
    }

    public static function getMimeType(): string
    {
        return 'image/tiff';
    }

    public static function getFormat(): string
    {
        return 'image-tiff';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
