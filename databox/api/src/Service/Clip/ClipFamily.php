<?php

declare(strict_types=1);

namespace App\Service\Clip;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use App\Model\BasketClip;

/**
 * Family of a file known only by its MIME type, for the decisions taken without the file
 * (API validation, Expose change detection). MediaClipper decides on the actual file.
 */
final class ClipFamily
{
    public static function fromMimeType(?string $mimeType): FamilyEnum
    {
        if (null === $mimeType || '' === $mimeType) {
            return FamilyEnum::Unknown;
        }

        if (str_starts_with($mimeType, 'image/')) {
            // SVG is returned untouched by the image module: nothing can be cropped
            return 'image/svg+xml' === $mimeType ? FamilyEnum::Unknown : FamilyEnum::Image;
        }

        // FileFamilyGuesser only reads the file for images
        return new FileFamilyGuesser()->getFamily('', $mimeType);
    }

    public static function restrict(?BasketClip $clip, ?string $mimeType): ?BasketClip
    {
        return $clip?->forFamily(self::fromMimeType($mimeType));
    }

    public static function acceptsTimeRange(?string $mimeType): bool
    {
        return in_array(self::fromMimeType($mimeType), [FamilyEnum::Video, FamilyEnum::Audio], true);
    }

    public static function acceptsCrop(?string $mimeType): bool
    {
        return in_array(self::fromMimeType($mimeType), [FamilyEnum::Image, FamilyEnum::Video], true);
    }
}
