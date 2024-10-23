<?php

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\X264;
use FFMpeg\Format\VideoInterface;

class QuicktimeFormat implements FormatInterface
{
    private VideoInterface $format;

    public function __construct()
    {
        $this->format = new X264();
    }

    public static function getAllowedExtensions(): array
    {
        return ['mov'];
    }

    public static function getMimeType(): string
    {
        return 'video/quicktime';
    }

    public static function getFormat(): string
    {
        return 'video-quicktime';
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
