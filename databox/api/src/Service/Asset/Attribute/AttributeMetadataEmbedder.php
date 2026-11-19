<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\RenditionDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Metadata\WritableMetadataPolicy;
use PHPExiftool\Driver\Metadata\MetadataBag;
use Psr\Log\LoggerInterface;

/**
 * Builds a metadata bag from an asset's attribute values, according to the "writeMetadata"
 * configuration of the attribute definitions.
 *
 * Used when exporting an asset (or one of its renditions) to embed the attribute values into
 * the exported file metadata. A definition may restrict itself to a subset of the renditions
 * through "writeMetadataRenditions".
 */
readonly class AttributeMetadataEmbedder
{
    public function __construct(
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributesResolver $attributesResolver,
        private MetadataManipulator $metadataManipulator,
        private WritableMetadataPolicy $writableMetadataPolicy,
        private LoggerInterface $logger,
    ) {
    }

    public function buildMetadataBag(Asset $asset, ?RenditionDefinition $renditionDefinition = null): ?MetadataBag
    {
        $definitions = $this->attributeDefinitionRepository->getWorkspaceWriteMetadataDefinitions(
            $asset->getWorkspaceId(),
            $renditionDefinition?->getId(),
        );
        if (empty($definitions)) {
            return null;
        }

        // Collect attribute values per definition id.
        $valuesByDefinition = [];
        foreach ($this->attributesResolver->resolveAssetAttributes($asset, false)->getFlattenAttributes() as $attribute) {
            $value = $attribute->getValue();
            if (null === $value || '' === trim($value)) {
                continue;
            }
            $valuesByDefinition[$attribute->getDefinition()->getId()][] = $value;
        }

        $bag = new MetadataBag();

        foreach ($definitions as $definition) {
            $values = $valuesByDefinition[$definition->getId()] ?? [];
            if (empty($values)) {
                continue;
            }

            foreach ($definition->getWriteMetadata() ?? [] as $tagGroupId) {
                try {
                    $meta = $this->metadataManipulator->createMetadata($tagGroupId);
                } catch (\Throwable $e) {
                    $this->logger->warning('Unknown metadata tag in attribute definition', [
                        'exception' => $e,
                        'attributeDefinition' => $definition->getId(),
                        'tag' => $tagGroupId,
                    ]);

                    continue;
                }

                if (!$meta->getTagGroup()->isWritable()) {
                    continue;
                }

                if (!$this->writableMetadataPolicy->isWritable($meta->getTagGroup()->getId())) {
                    $this->logger->warning('Metadata tag not allowed to be written in attribute definition', [
                        'attributeDefinition' => $definition->getId(),
                        'tag' => $tagGroupId,
                    ]);

                    continue;
                }

                if ($meta->getTagGroup()->isMulti()) {
                    $meta->setValue($values);
                } else {
                    $meta->setValue(reset($values));
                }

                $bag->add($meta);
            }
        }

        return count($bag) > 0 ? $bag : null;
    }
}
