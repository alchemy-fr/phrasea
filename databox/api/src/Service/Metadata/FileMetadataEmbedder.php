<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use PHPExiftool\Driver\Metadata\MetadataBag;

/**
 * Builds a metadata bag from the metadata the application has overridden on a file
 * (FileOverriddenMetadata).
 *
 * The metadata read from the file are deliberately left out: they already are in the file,
 * and re-writing them would make the application the source of truth for values it only ever
 * read. Only our own changes are written back.
 */
final readonly class FileMetadataEmbedder
{
    public function __construct(
        private MetadataNormalizer $metadataNormalizer,
    ) {
    }

    /**
     * The overrides describe the source file, so they are only embedded when that very file is
     * exported. A rendition is a derived file: it gets the attribute values, not the metadata
     * the application set on the original.
     */
    public function buildExportedFileMetadataBag(Asset $asset, File $exportedFile): ?MetadataBag
    {
        $source = $asset->getSource();
        if (null === $source || $exportedFile->getId() !== $source->getId()) {
            return null;
        }

        return $this->buildMetadataBag($source);
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
