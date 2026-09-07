<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Attribute\AttributeInterface;
use App\Attribute\InvalidAttributeValueException;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\File\StringableMetadataValue;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\Index\AttributeIndex;

readonly class InitialAttributeValuesResolver
{
    public function __construct(
        private AttributeValueResolver $attributeValueResolver,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeAssigner $attributeAssigner,
    ) {
    }

    /**
     * @return Attribute[]
     */
    public function resolveInitialAttributes(Asset $asset, ?AttributeDefinition $onlyDefinition = null): array
    {
        $attributes = [];
        $now = new \DateTimeImmutable();

        $definitions = $this->attributeDefinitionRepository->getWorkspaceInitializeDefinitions($asset->getWorkspaceId());

        foreach ($definitions as $definition) {
            if (null !== $onlyDefinition && $definition->getId() !== $onlyDefinition->getId()) {
                continue;
            }

            $readFromMetadata = $definition->getReadFromMetadata();
            if (null !== $readFromMetadata) {
                if (null !== $asset->getSource()?->getMetadata()) {
                    $initialValues = $this->resolveFromMetadata($asset->getSource(), $readFromMetadata, $definition);
                    $created = $this->createAttributes($asset, $definition, $now, AttributeInterface::NO_LOCALE, $initialValues);
                    foreach ($created as $attribute) {
                        $attributes[] = $attribute;
                    }
                }
            }

            $initializers = $definition->getInitialValues();
            if (null !== $initializers) {
                $attributeIndex = new AttributeIndex();

                foreach ($initializers as $locale => $initializeFormula) {
                    $this->attributeValueResolver->resolveAttrValues(
                        fn (AttributeDefinition $definition) => $definition->getInitialValues(),
                        Attribute::ORIGIN_INITIAL,
                        $asset,
                        $locale,
                        $definition,
                        $attributeIndex,
                    );
                }

                foreach ($attributeIndex->getFlattenAttributes() as $attribute) {
                    $attribute->setCreatedAt($now);
                    $attribute->setUpdatedAt($now);
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }

    /**
     * @param string[] $initialValues
     */
    private function createAttributes(
        Asset $asset,
        AttributeDefinition $definition,
        \DateTimeImmutable $now,
        string $locale,
        array $initialValues,
    ): array {
        $attributes = [];
        $position = 0;
        foreach ($initialValues as $initialValue) {
            try {
                $normalizedValue = $this->attributeAssigner->normalizeValue($definition, $initialValue);
            } catch (InvalidAttributeValueException) {
                // this can happen for e.g. if a date is invalid and cannot be normalized
                continue;
            }

            if (null === $normalizedValue) {
                continue;
            }

            $input = new AttributeInput();
            $input->value = $initialValue;
            $input->locale = $locale;
            $input->asset = $asset;
            $input->origin = Attribute::ORIGIN_LABELS[Attribute::ORIGIN_INITIAL];
            $input->definitionId = $definition->getId();
            $input->position = $position++;
            $input->status = Attribute::STATUS_VALID;

            $attribute = new Attribute();
            $attribute->setDefinition($definition);
            $attribute->setCreatedAt($now);
            $attribute->setUpdatedAt($now);
            $attribute->setAsset($asset);

            $this->attributeAssigner->assignAttributeFromInput($attribute, $input, $normalizedValue);
            $this->attributeAssigner->resetAssetAttributesCache($asset);

            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * Return the values of the first metadata tag found in the file, among the given list.
     *
     * @param string[] $tags
     *
     * @return string[]
     */
    private function resolveFromMetadata(
        File $file,
        array $tags,
        AttributeDefinition $definition,
    ): array {
        foreach ($tags as $tag) {
            $values = $file->getMetadataNameValues($tag);
            if (empty($values)) {
                continue;
            }

            $initialValues = $definition->isMultiple() ? $values : [implode(StringableMetadataValue::MULTIVALUE_SEPARATOR, $values)];

            return $this->filterEmptyValues($initialValues);
        }

        return [];
    }

    /**
     * @param array<?string> $values
     *
     * @return string[]
     */
    private function filterEmptyValues(array $values): array
    {
        return array_filter(
            $values,
            function (?string $s): bool {
                if (null === $s) {
                    return false;
                }

                return !empty(trim($s));
            });
    }
}
