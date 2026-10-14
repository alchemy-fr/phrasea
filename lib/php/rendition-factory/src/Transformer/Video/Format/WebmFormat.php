<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\VideoInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'video-webm')]
class WebmFormat implements FormatInterface
{
    private VideoInterface $format;

    public function __construct()
    {
        $this->format = new WebM();
    }

    public static function getAllowedExtensions(): array
    {
        return ['webm'];
    }

    public static function getMimeType(): string
    {
        return 'video/webm';
    }

    public static function getFormat(): string
    {
        return 'video-webm';
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
