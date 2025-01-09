<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

class PngFormat implements FormatInterface
{
    public static function getAllowedExtensions(): array
    {
        return ['png'];
    }

    public static function getMimeType(): string
    {
        return 'image/png';
    }

    public static function getFormat(): string
    {
        return 'image-png';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
