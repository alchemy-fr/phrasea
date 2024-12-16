<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

class GifFormat implements FormatInterface
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
        return 'image-gif';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
