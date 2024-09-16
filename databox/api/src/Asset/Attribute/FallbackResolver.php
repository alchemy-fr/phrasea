<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Asset\Attribute\Index\AttributeIndex;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

class FallbackResolver
{
    private ?array $indexByName = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TemplateResolver $templateResolver,
    )
    {
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
        AttributeIndex $attributesIndex
    ): ?Attribute {
        $definitionsIndex = $this->getDefinitionIndexByName($asset->getWorkspaceId());
        $fallbacks = $definition->getFallback();

        if (!empty($fallbacks[$locale])) {
            if (null === $attributesIndex->getAttribute($definition->getId(), $locale)) {
                $fallbackValue = $this->templateResolver->resolve($fallbacks[$locale], [
                    'file' => $asset->getSource(),
                    'asset' => $asset,
                    'attr' => new DynamicAttributeBag($attributesIndex, $definitionsIndex, function (AttributeDefinition $depDef) use (
                        $asset,
                        $attributesIndex,
                        $locale
                    ): ?Attribute {
                        return $this->resolveAttrFallback(
                            $asset,
                            $locale,
                            $depDef,
                            $attributesIndex
                        );
                    }, $locale),
                ]);

                $attribute = new Attribute();
                $attribute->setCreatedAt(new \DateTimeImmutable());
                $attribute->setUpdatedAt(new \DateTimeImmutable());
                $attribute->setLocale($locale);
                $attribute->setDefinition($definition);
                $attribute->setAsset($asset);
                $attribute->setOrigin(Attribute::ORIGIN_FALLBACK);
                $attribute->setValue($fallbackValue);

                $attributesIndex->addAttribute($attribute);

                return $attribute;
            }
        }

        return null;
    }
}
