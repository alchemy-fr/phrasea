<?php

declare(strict_types=1);

namespace App\Service\Clip;

use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use App\Model\MediaClip;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

    public static function restrict(?MediaClip $clip, ?string $mimeType): ?MediaClip
    {
        return $clip?->forFamily(self::fromMimeType($mimeType));
    }

    /**
     * @param string|null $mimeType the type of the asset source file, null when there is none
     *
     * @throws BadRequestHttpException when a part of the clip does not apply to the file
     */
    public static function assertApplicable(MediaClip $clip, ?string $mimeType): void
    {
        if (null === $mimeType) {
            throw new BadRequestHttpException('The asset has no source file to clip');
        }
        if ($clip->hasTimeRange() && !self::acceptsTimeRange($mimeType)) {
            throw new BadRequestHttpException(sprintf('A time range only applies to a video or an audio file, not to "%s"', $mimeType));
        }
        if ($clip->hasCrop() && !self::acceptsCrop($mimeType)) {
            throw new BadRequestHttpException(sprintf('A crop only applies to an image or a video file, not to "%s"', $mimeType));
        }
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
