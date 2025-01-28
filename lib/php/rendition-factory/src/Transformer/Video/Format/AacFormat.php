<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Transformer\Video\Format\Audio\Aac;
use FFMpeg\Format\AudioInterface;

class AacFormat implements FormatInterface
{
    private AudioInterface $format;

    public function __construct()
    {
        $this->format = new Aac();
    }

    public static function getAllowedExtensions(): array
    {
        return ['aac', 'm4a'];
    }

    public static function getMimeType(): string
    {
        return 'audio/aac';
    }

    public static function getFormat(): string
    {
        return 'audio-aac';
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
