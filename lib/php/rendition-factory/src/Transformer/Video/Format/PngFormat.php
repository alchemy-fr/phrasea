<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::FORMAT)]
class PngFormat implements FormatInterface
{
    public const string FORMAT = 'image-png';

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
        return self::FORMAT;
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
