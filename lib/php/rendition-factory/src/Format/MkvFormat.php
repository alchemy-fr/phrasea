<?php

namespace Alchemy\RenditionFactory\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\VideoInterface;

class MkvFormat implements FormatInterface
{
    private VideoInterface $format;

    public function __construct()
    {
        $this->format = new X264();
    }

    public static function getAllowedExtensions(): array
    {
        return ['mkv'];
    }

    public static function getMimeType(): string
    {
        return 'video/x-matroska';
    }

    public static function getFormat(): string
    {
        return 'video-mkv';
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
