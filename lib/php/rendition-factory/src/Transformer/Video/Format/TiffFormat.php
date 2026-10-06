<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'image-tiff')]
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
