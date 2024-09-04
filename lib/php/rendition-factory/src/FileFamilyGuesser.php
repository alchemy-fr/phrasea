<?php

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

final readonly class FileFamilyGuesser
{
    public function getFamily(string $mimeType): FamilyEnum
    {
        if (str_starts_with($mimeType, 'image/')) {
            return match ($mimeType) {
                'image/svg+xml' => FamilyEnum::Svg,
                'image/gif' => FamilyEnum::Gif,
                default => FamilyEnum::Image,
            };
        } elseif (str_starts_with($mimeType, 'video/')) {
            return FamilyEnum::Video;
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return FamilyEnum::Audio;
        }

        return match ($mimeType) {
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => FamilyEnum::Document,
            default => FamilyEnum::Unknown,
        };
    }
}
