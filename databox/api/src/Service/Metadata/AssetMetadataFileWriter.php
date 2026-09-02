<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\Asset;
use App\Service\Asset\Attribute\AttributeMetadataEmbedder;
use Psr\Log\LoggerInterface;

/**
 * Writes an asset's attribute metadata into a file on disk (in place).
 */
final readonly class AssetMetadataFileWriter
{
    public function __construct(
        private AttributeMetadataEmbedder $attributeMetadataEmbedder,
        private MetadataManipulator $metadataManipulator,
        private LoggerInterface $logger,
    ) {
    }

    public function writeAssetMetadata(string $path, Asset $asset): void
    {
        $bag = $this->attributeMetadataEmbedder->buildMetadataBag($asset);
        if (null === $bag || 0 === $bag->count()) {
            return;
        }

        try {
            $writer = $this->metadataManipulator->createWriter();

            $tmpFile = sys_get_temp_dir().'/'.uniqid('metadata-file');
            $writer->write($path, $bag, destination: $tmpFile);
            unlink($path);
            rename($tmpFile, $path);
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
