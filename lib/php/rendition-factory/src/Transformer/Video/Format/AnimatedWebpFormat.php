<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::FORMAT)]
class AnimatedWebpFormat implements FormatInterface
{
    public const string FORMAT = 'animated-webp';

    public static function getAllowedExtensions(): array
    {
        return ['webp'];
    }

    public static function getMimeType(): string
    {
        return 'image/webp';
    }

    public static function getFormat(): string
    {
        return self::FORMAT;
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Animation;
    }
}
