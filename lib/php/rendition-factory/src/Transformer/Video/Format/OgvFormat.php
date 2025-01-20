<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\Ogg;
use FFMpeg\Format\VideoInterface;

class OgvFormat implements FormatInterface
{
    private VideoInterface $format;

    public function __construct()
    {
        $this->format = new Ogg();
    }

    public static function getAllowedExtensions(): array
    {
        return ['ogv'];
    }

    public static function getMimeType(): string
    {
        return 'video/ogg';
    }

    public static function getFormat(): string
    {
        return 'video-ogg';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Video;
    }

    public function getFFMpegFormat(): VideoInterface
    {
        return $this->format;
    }
}
