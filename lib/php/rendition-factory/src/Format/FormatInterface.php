<?php

namespace Alchemy\RenditionFactory\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

interface FormatInterface
{
    final public const TAG = 'alchemy_rendition_factory.ffmpeg_format';

    public static function getAllowedExtensions(): array;

    public static function getMimeType(): string;

    public static function getFormat(): string;

    public static function getFamily(): FamilyEnum;
}
