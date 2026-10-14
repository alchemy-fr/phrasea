<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'animated-webp')]
class AnimatedWebpFormat implements FormatInterface
{
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
        return 'animated-webp';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Animation;
    }
}
