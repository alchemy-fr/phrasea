<?php

declare(strict_types=1);

namespace App\Service\Metadata;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\RenditionDefinition;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Psr\Log\LoggerInterface;

/**
 * Builds a metadata bag from the hardcoded metadata configured on a rendition definition.
 *
 * These values are written into the exported rendition file.
 */
final readonly class RenditionDefinitionMetadataEmbedder
{
    public function __construct(
        private MetadataManipulator $metadataManipulator,
        private LoggerInterface $logger,
    ) {
    }

    public function buildMetadataBag(RenditionDefinition $definition): ?MetadataBag
    {
        $map = $definition->getMetadata();
        if ([] === $map) {
            return null;
        }

        $bag = new MetadataBag();

        foreach ($map as $tagGroupId => $value) {
            if (str_starts_with($tagGroupId, 'System:')) {
                continue;
            }

            try {
                $meta = $this->metadataManipulator->createMetadata($tagGroupId);
            } catch (\Throwable $e) {
                $this->logger->warning('Unknown metadata tag in rendition definition', [
                    'exception' => $e,
                    'renditionDefinition' => $definition->getId(),
                    'tag' => $tagGroupId,
                ]);

                continue;
            }

            $tagGroup = $meta->getTagGroup();
            if (!$tagGroup->isWritable()) {
                continue;
            }

            $value = (string) $value;
            $meta->setValue($tagGroup->isMulti() ? [$value] : $value);
            $bag->set($tagGroup->getId(), $meta);
        }

        return count($bag) > 0 ? $bag : null;
    }
}
