<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Repository\Core\AttributeDefinitionRepository;
use PHPExiftool\Driver\Metadata\MetadataBag;

/**
 * Builds a metadata bag from an asset's attribute values, according to the
 * "writeMetadata" configuration of the attribute definitions.
 *
 * Used when exporting an asset (or one of its renditions) to embed the attribute
 * values into the exported file metadata.
 */
readonly class AttributeMetadataEmbedder
{
    public function __construct(
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributesResolver $attributesResolver,
        private MetadataManipulator $metadataManipulator,
    ) {
    }

    public function buildMetadataBag(Asset $asset): ?MetadataBag
    {
        $definitions = $this->attributeDefinitionRepository->getWorkspaceWriteMetadataDefinitions($asset->getWorkspaceId());
        if ([] === $definitions) {
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
            if ([] === $values) {
                continue;
            }

            foreach ($definition->getWriteMetadata() ?? [] as $tagGroupId) {
                $meta = $this->metadataManipulator->createMetadata($tagGroupId);
                if (!$meta->getTagGroup()->isWritable() || str_starts_with($tagGroupId, 'System:')) {
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
