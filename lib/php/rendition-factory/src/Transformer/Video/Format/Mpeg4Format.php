<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\VideoInterface;

class Mpeg4Format implements FormatInterface
{
    private VideoInterface $format;

    public function __construct()
    {
        $this->format = new X264();
    }

    public static function getAllowedExtensions(): array
    {
        return ['mp4'];
    }

    public static function getMimeType(): string
    {
        return 'video/mp4';
    }

    public static function getFormat(): string
    {
        return 'video-mpeg4';
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
