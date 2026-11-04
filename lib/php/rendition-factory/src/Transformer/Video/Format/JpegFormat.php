<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::FORMAT)]
class JpegFormat implements FormatInterface
{
    public const string FORMAT = 'image-jpeg';

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
        return self::FORMAT;
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
