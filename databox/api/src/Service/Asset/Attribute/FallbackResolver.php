<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Notification\EntityDisableNotifyableException;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use Doctrine\ORM\EntityManagerInterface;

class FallbackResolver
{
    private ?array $indexByName = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TemplateResolver $templateResolver,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    private function getDefinitionIndexByName(string $workspaceId): array
    {
        if (null !== $this->indexByName) {
            return $this->indexByName;
        }

        $definitions = $this->em->getRepository(AttributeDefinition::class)->getWorkspaceDefinitions($workspaceId);
        $this->indexByName = [];

        foreach ($definitions as $definition) {
            $this->indexByName[$definition->getSlug()] = $definition;
        }

        return $this->indexByName;
    }

    public function resolveAttrFallback(
        Asset $asset,
        string $locale,
        AttributeDefinition $definition,
        AttributeIndex $attributesIndex,
        array $parentDefinitions = [],
    ): ?Attribute {
        $definitionsIndex = $this->getDefinitionIndexByName($asset->getWorkspaceId());
        $fallbacks = $definition->getFallback();

        if (!$definition->isEnabled()) {
            return null;
        }

        if ($definition->isMultiple()) {
            return null;
        }

        $parentDefinitions[] = $definition->getId();

        if (!empty($fallbacks[$locale])) {
            if (null === $attributesIndex->getAttribute($definition->getId(), $locale)) {
                try {
                    $fallbackValue = $this->templateResolver->resolve($fallbacks[$locale], [
                        'file' => $asset->getSource(),
                        'asset' => $asset,
                        'attr' => new DynamicAttributeBag(
                            $attributesIndex,
                            $definitionsIndex,
                            fn (AttributeDefinition $depDef): ?Attribute => $this->resolveAttrFallback(
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
                    throw new EntityDisableNotifyableException($definition, sprintf('Error while resolving "%s" (locale=%s) attribute fallback', $definition->getName(), $locale), $e->getMessage(), previous: $e);
                }

                $type = $this->attributeTypeRegistry->getType($definition->getType());

                $normalizedValue = $type->normalizeValue($fallbackValue);
                if (null === $normalizedValue) {
                    return null;
                }
                $isInvalid = !empty($type->validate($normalizedValue));
                $value = $type->convertToDbValue($normalizedValue);
                if ($isInvalid && !$definition->isAllowInvalid()) {
                    throw new EntityDisableNotifyableException($definition, sprintf('Invalid value "%s" for "%s" (locale=%s) attribute fallback', $value, $definition->getName(), $locale), sprintf('Invalid value "%s"', $value));
                }

                $attribute = new Attribute();
                $now = new \DateTimeImmutable();
                $attribute->setCreatedAt($now);
                $attribute->setUpdatedAt($now);
                $attribute->setLocale($locale);
                $attribute->setDefinition($definition);
                $attribute->setAsset($asset);
                $attribute->setOrigin(Attribute::ORIGIN_FALLBACK);
                $attribute->setValue($value);
                $attribute->setInvalid($isInvalid);

                $attributesIndex->addAttribute($attribute);

                return $attribute;
            }
        }

        return null;
    }
}
