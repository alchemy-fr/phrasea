<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::FORMAT)]
class TiffFormat implements FormatInterface
{
    public const string FORMAT = 'image-tiff';

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
        return self::FORMAT;
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
