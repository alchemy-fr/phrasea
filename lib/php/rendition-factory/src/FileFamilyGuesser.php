<?php

namespace Alchemy\RenditionFactory;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

final readonly class FileFamilyGuesser
{
    public function getFamily(string $src, string $mimeType): FamilyEnum
    {
        if (str_starts_with($mimeType, 'image/')) {
            switch ($mimeType) {
                case 'image/svg+xml':
                    if (str_contains(file_get_contents($src), '<animate')) {
                        return FamilyEnum::Animation;
                    }

                    return FamilyEnum::Image;
                case 'image/gif':
                    if ($this->isAnimatedGif($src)) {
                        return FamilyEnum::Animation;
                    }

                    return FamilyEnum::Image;
                case 'image/webp':
                    if ($this->isWebpAnimated($src)) {
                        return FamilyEnum::Animation;
                    }

                    return FamilyEnum::Image;
                default:
                    return FamilyEnum::Image;
            }
        } elseif (str_starts_with($mimeType, 'video/')) {
            return FamilyEnum::Video;
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return FamilyEnum::Audio;
        }

        return match ($mimeType) {
            'application/pdf',
            'text/rtf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.spreadsheet' => FamilyEnum::Document,

            'application/mxf', 'application/ogg' => FamilyEnum::Video,

            'application/x-photoshop',
            'application/photoshop',
            'application/psd' => FamilyEnum::Image,
            'application/vnd.3gpp.pic-bw-small' => FamilyEnum::Image,
            'application/illustrator' => FamilyEnum::Image,

            default => FamilyEnum::Unknown,
        };
    }

    private function isAnimatedGif(string $src): bool
    {
        if (!($fh = @fopen($src, 'rb'))) {
            return false;
        }
        $count = 0;

        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); // read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk);
        }

        fclose($fh);

        return $count > 1;
    }

    private function isWebpAnimated(string $src): bool
    {
        $result = false;
        $fh = fopen($src, 'rb');
        fseek($fh, 12);
        if ('VP8X' === fread($fh, 4)) {
            fseek($fh, 16);
            $myByte = fread($fh, 1);
            $result = (bool) ((ord($myByte) >> 1) & 1);
        }
        fclose($fh);

        return $result;
    }
}
