<?php

namespace App\Service\Asset\Attribute;

use App\Attribute\AttributeTypeRegistry;
use App\Attribute\Type\AttributeTypeInterface;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\File\FileMetadataAccessorWrapper;
use App\Notification\EntityDisableNotifyableException;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\Index\AttributeIndex;

final class AttributeValueResolver
{
    /**
     * @var array<string, array<string, AttributeDefinition>> definitions indexed by slug, per workspace
     */
    private array $indexByName = [];

    public function __construct(
        private readonly TemplateResolver $templateResolver,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    private function getDefinitionIndexByName(string $workspaceId): array
    {
        if (isset($this->indexByName[$workspaceId])) {
            return $this->indexByName[$workspaceId];
        }

        $definitions = $this->attributeDefinitionRepository->getWorkspaceDefinitions($workspaceId);
        $index = [];

        foreach ($definitions as $definition) {
            $index[$definition->getSlug()] = $definition;
        }

        return $this->indexByName[$workspaceId] = $index;
    }

    /**
     * @param callable $getTemplates function(AttributeDefinition): ?array<string, string>  returns an array of
     *                               templates indexed by locale
     */
    public function resolveAttrValues(
        callable $getTemplates,
        int $origin,
        Asset $asset,
        string $locale,
        AttributeDefinition $definition,
        AttributeIndex $attributesIndex,
        array $parentDefinitions = [],
    ): array {
        if (!$definition->isEnabled()) {
            return [];
        }
        $isMultiple = $definition->isMultiple();
        $attributes = [];

        $definitionsIndex = $this->getDefinitionIndexByName($asset->getWorkspaceId());
        /** @var ?array<string, string> $templates */
        $templates = $getTemplates($definition);
        if (null === $templates) {
            return [];
        }

        $parentDefinitions[] = $definition->getId();

        if (!empty($templates[$locale])) {
            $hasAttribute = $isMultiple ? !empty($attributesIndex->getAttributes($definition->getId(), $locale)) : null !== $attributesIndex->getAttribute($definition->getId(), $locale);

            if (!$hasAttribute) {
                try {
                    $resolvedValue = $this->templateResolver->resolve($templates[$locale], [
                        'file' => new FileMetadataAccessorWrapper($asset->getSource()),
                        'asset' => $asset,
                        'attr' => new DynamicAttributeBag(
                            $attributesIndex,
                            $definitionsIndex,
                            fn (AttributeDefinition $depDef): array => $this->resolveAttrValues(
                                $getTemplates,
                                $origin,
                                $asset,
                                $locale,
                                $depDef,
                                $attributesIndex,
                                $parentDefinitions,
                            ),
                            $locale,
                            $parentDefinitions,
                        ),
                    ]);
                } catch (\Throwable $e) {
                    throw new EntityDisableNotifyableException($definition, sprintf('Error while resolving "%s" (locale=%s) attribute %s value', $definition->getName(), $locale, Attribute::ORIGIN_LABELS[$origin] ?? $origin), $e->getMessage(), previous: $e);
                }

                $type = $this->attributeTypeRegistry->getType($definition->getType());

                if ($isMultiple) {
                    $position = 0;
                    foreach (explode("\n", $resolvedValue) as $row) {
                        $a = $this->createAttributeFromValue(
                            $attributesIndex,
                            $asset,
                            $locale,
                            $definition,
                            $type,
                            $origin,
                            $row,
                            $position
                        );
                        if (null !== $a) {
                            ++$position;
                            $attributes[] = $a;
                        }
                    }
                } else {
                    $a = $this->createAttributeFromValue(
                        $attributesIndex,
                        $asset,
                        $locale,
                        $definition,
                        $type,
                        $origin,
                        $resolvedValue,
                        0
                    );
                    if (null !== $a) {
                        $attributes[] = $a;
                    }
                }
            }
        }

        return $attributes;
    }

    private function createAttributeFromValue(
        AttributeIndex $attributesIndex,
        Asset $asset,
        string $locale,
        AttributeDefinition $definition,
        AttributeTypeInterface $type,
        int $origin,
        mixed $value,
        int $position,
    ): ?Attribute {
        $normalizedValue = $type->normalizeValue($value);
        if (null === $normalizedValue) {
            return null;
        }
        $isInvalid = !empty($type->validate($normalizedValue));
        $value = $type->convertToDbValue($normalizedValue);
        if ($isInvalid && !$definition->isAllowInvalid()) {
            throw new EntityDisableNotifyableException($definition, sprintf('Invalid value "%s" for "%s" (locale=%s) attribute %s value', $value, $definition->getName(), $locale, Attribute::ORIGIN_LABELS[$origin] ?? $origin), sprintf('Invalid value "%s"', $value));
        }

        $attribute = new Attribute();
        $now = new \DateTimeImmutable();
        $attribute->setCreatedAt($now);
        $attribute->setUpdatedAt($now);
        $attribute->setLocale($locale);
        $attribute->setDefinition($definition);
        $attribute->setAsset($asset);
        $attribute->setOrigin($origin);
        $attribute->setValue($value);
        $attribute->setInvalid($isInvalid);
        $attribute->setPosition($position);

        $attributesIndex->addAttribute($attribute);

        return $attribute;
    }
}
