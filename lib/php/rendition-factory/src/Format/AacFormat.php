<?php

namespace Alchemy\RenditionFactory\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\Format\Audio\Aac;

class AacFormat implements FormatInterface
{
    private Aac $format;

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

    public function getFFMpegFormat(): Aac
    {
        return $this->format;
    }
}
