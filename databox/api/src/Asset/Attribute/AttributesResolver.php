<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

class AttributesResolver
{
    private EntityManagerInterface $em;
    private FieldNameResolver $fieldNameResolver;
    private FallbackResolver $fallbackResolver;

    public function __construct(
        EntityManagerInterface $em,
        FieldNameResolver $fieldNameResolver,
        FallbackResolver $fallbackResolver
    ) {
        $this->em = $em;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->fallbackResolver = $fallbackResolver;
    }

    /**
     * @return array<string, array<string, Attribute>>
     */
    public function resolveAttributes(Asset $asset): array
    {
        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)
            ->getAssetAttributes($asset);

        /** @var array<string, array<string, Attribute>> $groupedByDef */
        $groupedByDef = [];
        foreach ($attributes as $attribute) {
            $def = $attribute->getDefinition();
            $k = $def->getId();
            $locale = $attribute->getLocale() ?? IndexMappingUpdater::NO_LOCALE;

            if (!isset($groupedByDef[$k][$locale])) {
                if (!isset($groupedByDef[$k])) {
                    $groupedByDef[$k] = [];
                }

                $groupedByDef[$k][$locale] = $attribute;
            }

            if ($def->isMultiple()) {
                $values = $groupedByDef[$k][$locale]->getValues() ?? [];
                $values[] = $attribute->getValue();
                $groupedByDef[$k][$locale]->setValues($values);
            }
        }
        unset($attributes);

        return $this->resolveFallbacks($asset, $groupedByDef);
    }

    /**
     * @param array<string, array<string, Attribute>> $attributes
     *
     * @return array<string, array<string, Attribute>>
     */
    private function resolveFallbacks(Asset $asset, array $attributes): array
    {
        /** @var AttributeDefinition[] $fbDefinitions */
        $fbDefinitions = $this->em
            ->getRepository(AttributeDefinition::class)
            ->getWorkspaceFallbackDefinitions($asset->getWorkspaceId());

        foreach ($fbDefinitions as $definition) {
            $k = $definition->getId();

            $fallbacks = $definition->getFallback();
            if (null !== $fallbacks) {
                foreach ($fallbacks as $locale => $fb) {
                    if (!isset($attributes[$k][$locale])) {
                        $attr = $this->fallbackResolver->resolveAttrFallback(
                            $asset,
                            $locale,
                            $definition,
                            $attributes
                        );
                        if (null !== $attr) {
                            $attributes[$k][$locale] = $attr;
                        }
                    }
                }
            }
        }

        return $attributes;
    }

    public function assignHighlight(array $attributes, array $highlights): void
    {
        foreach ($attributes as $_attrs) {
            foreach ($_attrs as $locale => $attribute) {
                $f = $this->fieldNameResolver->getFieldName($attribute->getDefinition());

                $fieldName = sprintf('attributes.%s.%s', $locale, $f);

                if ($h = ($highlights[$fieldName] ?? null)) {
                    if ($attribute->getDefinition()->isMultiple()) {
                        $values = $attribute->getValues();
                        $newValues = [];

                        foreach ($values as $v) {
                            $found = false;
                            foreach ($highlights[$fieldName] as $hlValue) {
                                if (preg_replace('#\[hl](.*)\[/hl]#', '$1', $hlValue) === $v) {
                                    $found = true;
                                    $newValues[] = $hlValue;
                                    break;
                                }
                            }
                            if (!$found) {
                                $newValues[] = $v;
                            }
                        }

                        $attribute->setHighlights($newValues);
                    } else {
                        $attribute->setHighlight(reset($h));
                    }
                }
            }
        }
    }
}
