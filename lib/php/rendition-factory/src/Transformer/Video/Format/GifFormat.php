<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::FORMAT)]
class GifFormat implements FormatInterface
{
    public const string FORMAT = 'image-gif';

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
        return self::FORMAT;
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Image;
    }
}
