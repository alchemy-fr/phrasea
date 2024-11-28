<?php

namespace Alchemy\RenditionFactory\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Audio\Wav;

class WavFormat implements FormatInterface
{
    private Wav $format;

    public function __construct()
    {
        $this->format = new Wav();
    }

    public static function getAllowedExtensions(): array
    {
        return ['wav'];
    }

    public static function getMimeType(): string
    {
        return 'audio/wav';
    }

    public static function getFormat(): string
    {
        return 'audio-wav';
    }

    public static function getFamily(): FamilyEnum
    {
        return FamilyEnum::Audio;
    }

    public function getFFMpegFormat(): Wav
    {
        return $this->format;
    }
}
