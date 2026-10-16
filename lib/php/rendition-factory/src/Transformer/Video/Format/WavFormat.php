<?php

namespace Alchemy\RenditionFactory\Transformer\Video\Format;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use FFMpeg\Format\Audio\Wav;
use FFMpeg\Format\AudioInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: self::FORMAT)]
class WavFormat implements FormatInterface
{
    public const string FORMAT = 'audio-wav';

    private AudioInterface $format;

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
        return self::FORMAT;
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
