<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Psr\Log\LoggerInterface;

/**
 * Writes an asset's metadata into a file on disk (in place): the metadata the application
 * overrode on the source file, then the attribute values, which win over them.
 *
 * The metadata read from the source file are never written back.
 */
final readonly class AssetMetadataFileWriter
{
    public function __construct(
        private AttributeMetadataEmbedder $attributeMetadataEmbedder,
        private FileMetadataEmbedder $fileMetadataEmbedder,
        private MetadataManipulator $metadataManipulator,
        private LoggerInterface $logger,
    ) {
    }

    public function writeAssetMetadata(string $path, Asset $asset, ?RenditionDefinition $renditionDefinition = null): void
    {
        $bag = new MetadataBag();

        // only the metadata the application overrode on the source file, never the ones read from it
        foreach ($this->fileMetadataEmbedder->buildMetadataBag($asset->getSource()) ?? [] as $meta) {
            $bag->set($meta->getTagGroup()->getId(), $meta);
        }

        // attribute values win over the file metadata
        foreach ($this->attributeMetadataEmbedder->buildMetadataBag($asset, $renditionDefinition) ?? [] as $meta) {
            $bag->set($meta->getTagGroup()->getId(), $meta);
        }

        if (0 === $bag->count()) {
            return;
        }

        try {
            $writer = $this->metadataManipulator->createWriter();
            $writer->disableConversion();

            $tmpFile = tempnam(\dirname($path), 'metadata-file-');
            if (false === $tmpFile) {
                throw new \RuntimeException('Failed to create a temporary file for metadata writing.');
            }
            $writer->write($path, $bag, destination: $tmpFile);
            if (false === rename($tmpFile, $path)) {
                throw new \RuntimeException(sprintf('Failed to move temporary metadata file "%s" to "%s".', $tmpFile, $path));
            }
        } catch (\Throwable $e) {
            // The file format may not support metadata writing; skip embedding for this file.
            $this->logger->error('Failed to write metadata into file', [
                'exception' => $e,
                'assetId' => $asset->getId(),
                'path' => $path,
            ]);
        }
    }
}
