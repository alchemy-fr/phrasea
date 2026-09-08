<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\Asset;
use App\Entity\Core\RenditionDefinition;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use Psr\Log\LoggerInterface;

/**
 * Writes an asset's attribute metadata into a rendition file on disk (in place).
 *
 * Neither the metadata read from the source file nor the ones the application overrode on it
 * are written here: they describe the original, and a rendition is a derived file. They only
 * travel with the source when the source itself is exported.
 */
final readonly class AssetMetadataFileWriter
{
    public function __construct(
        private AttributeMetadataEmbedder $attributeMetadataEmbedder,
        private MetadataManipulator $metadataManipulator,
        private LoggerInterface $logger,
    ) {
    }

    public function writeAssetMetadata(string $path, Asset $asset, ?RenditionDefinition $renditionDefinition = null): void
    {
        $bag = $this->attributeMetadataEmbedder->buildMetadataBag($asset, $renditionDefinition);
        if (null === $bag || 0 === $bag->count()) {
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
