<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Audio\Vorbis;
use FFMpeg\Format\AudioInterface;

class OgaFormat implements FormatInterface
{
    private AudioInterface $format;

    public function __construct()
    {
        $this->format = new Vorbis();
    }

    public static function getAllowedExtensions(): array
    {
        return ['oga', 'ogg'];
    }

    public static function getMimeType(): string
    {
        return 'audio/ogg';
    }

    public static function getFormat(): string
    {
        return 'audio-ogg';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Audio;
    }

    public function getFFMpegFormat(): AudioInterface
    {
        return $this->format;
    }
}
