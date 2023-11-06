<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class AttributesResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private FieldNameResolver $fieldNameResolver,
        private FallbackResolver $fallbackResolver,
        private Security $security
    ) {
    }

    /**
     * @return array<string, array<string, Attribute>>
     */
    public function resolveAssetAttributes(Asset $asset, bool $applyPermissions): array
    {
        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)
            ->getAssetAttributes($asset);

        $groupedByDef = $this->groupAttributesByLocale($attributes, $applyPermissions);

        return $this->resolveFallbacks($asset, $groupedByDef);
    }

    /**
     * @param AbstractBaseAttribute[] $attributes
     *
     * @return array<string, array<string, AbstractBaseAttribute>>
     */
    public function groupAttributesByLocale(iterable $attributes, bool $applyPermissions): array
    {
        $disallowedDefinitions = [];

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

                $groupedByDef[$k][$locale] = clone $attribute;
                $attribute->setValues(null); // Reset values aggregation

                if ($applyPermissions
                    && !isset($disallowedDefinitions[$k])
                ) {
                    assert($attribute instanceof Attribute);
                    $disallowedDefinitions[$k] = !$this->security->isGranted(AbstractVoter::READ, $attribute);
                }
            }

            $groupAttr = $groupedByDef[$k][$locale];

            if ($def->isMultiple()) {
                $values = $groupAttr->getValues() ?? [];
                $values[] = $attribute->getValue();
                $groupAttr->setValues($values);
            }
        }
        unset($attributes);

        if ($applyPermissions) {
            $disallowedDefinitions = array_filter($disallowedDefinitions, fn (bool $v): bool => $v);
            $groupedByDef = array_diff_key($groupedByDef, $disallowedDefinitions);
        }

        return $groupedByDef;
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
                                if (preg_replace('#\[hl](.*)\[/hl]#', '$1', (string) $hlValue) === $v) {
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
