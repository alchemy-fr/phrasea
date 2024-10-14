<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Asset\Attribute\Index\AttributeIndex;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AttributeDefinitionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class AttributesResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private FieldNameResolver $fieldNameResolver,
        private FallbackResolver $fallbackResolver,
        private Security $security,
    ) {
    }

    public function resolveAssetAttributes(Asset $asset, bool $applyPermissions): AttributeIndex
    {
        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)
            ->getAssetAttributes($asset->getId());

        $index = $this->buildIndex($attributes);
        $this->resolveFallbacks($asset, $index);

        if ($applyPermissions) {
            foreach ($index->getDefinitions() as $definitionIndex) {
                $definition = $definitionIndex->getDefinition();
                if (!$this->security->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition)) {
                    $index->removeDefinition($definition->getId());
                }
            }
        }

        return $index;
    }

    /**
     * @param Attribute[] $attributes
     */
    private function buildIndex(array $attributes): AttributeIndex
    {
        $index = new AttributeIndex();
        foreach ($attributes as $attribute) {
            $index->addAttribute($attribute);
        }

        return $index;
    }

    private function resolveFallbacks(Asset $asset, AttributeIndex $attributes): void
    {
        /** @var AttributeDefinition[] $fbDefinitions */
        $fbDefinitions = $this->em
            ->getRepository(AttributeDefinition::class)
            ->getWorkspaceFallbackDefinitions($asset->getWorkspaceId());

        foreach ($fbDefinitions as $definition) {
            if ($definition->isMultiple()) {
                continue;
            }
            $k = $definition->getId();

            $fallbacks = $definition->getFallback();
            if (null !== $fallbacks) {
                foreach ($fallbacks as $locale => $fb) {
                    if (null === $attributes->getAttribute($k, $locale)) {
                        $attr = $this->fallbackResolver->resolveAttrFallback(
                            $asset,
                            $locale,
                            $definition,
                            $attributes
                        );
                        if (null !== $attr) {
                            $attributes->addAttribute($attr);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Attribute[] $attributes
     */
    public function assignHighlight(array $attributes, array $highlights): void
    {
        foreach ($attributes as $attribute) {
            $locale = $attribute->getLocale() ?? AttributeInterface::NO_LOCALE;
            $definition = $attribute->getDefinition();
            $f = $this->fieldNameResolver->getFieldNameFromDefinition($definition);

            $fieldName = sprintf('%s.%s.%s', AttributeInterface::ATTRIBUTES_FIELD, $locale, $f);

            if ($h = ($highlights[$fieldName] ?? null)) {
                if ($definition->isMultiple()) {
                    $v = $attribute->getValue();
                    foreach ($h as $hlValue) {
                        if (preg_replace('#\[hl](.*)\[/hl]#', '$1', (string) $hlValue) === $v) {
                            $attribute->setHighlight($hlValue);
                            break;
                        }
                    }
                } else {
                    $attribute->setHighlight(reset($h));
                }
            }
        }
    }
}
