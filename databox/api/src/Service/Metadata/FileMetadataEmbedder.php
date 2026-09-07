<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use App\Entity\Core\File;
use PHPExiftool\Driver\Metadata\MetadataBag;

/**
 * Builds a metadata bag from the metadata the application has overridden on a file
 * (FileOverriddenMetadata).
 *
 * The metadata read from the file are deliberately left out: they already are in the file,
 * and re-writing them would make the application the source of truth for values it only ever
 * read. Only our own changes are written back into renditions and exports.
 */
final readonly class FileMetadataEmbedder
{
    public function __construct(
        private MetadataNormalizer $metadataNormalizer,
    ) {
    }

    public function buildMetadataBag(?File $file): ?MetadataBag
    {
        $overridden = $file?->getOverriddenMetadata() ?? [];
        if ([] === $overridden) {
            return null;
        }

        // denormalize() drops the System namespace, the non-writable and the unknown tags
        $bag = $this->metadataNormalizer->denormalize($overridden);

        return count($bag) > 0 ? $bag : null;
    }
}
