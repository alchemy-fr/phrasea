<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

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
