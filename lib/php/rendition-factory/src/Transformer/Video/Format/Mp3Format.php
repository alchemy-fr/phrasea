<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\AudioInterface;

class Mp3Format implements FormatInterface
{
    private AudioInterface $format;

    public function __construct()
    {
        $this->format = new Mp3();
    }

    public static function getAllowedExtensions(): array
    {
        return ['mp3'];
    }

    public static function getMimeType(): string
    {
        return 'audio/mp3';
    }

    public static function getFormat(): string
    {
        return 'audio-mp3';
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
