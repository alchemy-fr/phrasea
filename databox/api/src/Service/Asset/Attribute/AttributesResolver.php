<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Notification\ExceptionNotifier;
use App\Notification\UserNotifyableException;
use App\Security\Voter\AttributeDefinitionVoter;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;

readonly class AttributesResolver
{
    private CacheInterface $definitionPermissionCache;

    public function __construct(
        private EntityManagerInterface $em,
        private FieldNameResolver $fieldNameResolver,
        private FallbackResolver $fallbackResolver,
        private Security $security,
        private ExceptionNotifier $exceptionNotifier,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->definitionPermissionCache = $cacheFactory->createCache();
    }

    public function resolveAssetAttributes(Asset $asset, bool $applyPermissions): AttributeIndex
    {
        if ($applyPermissions && $asset->attributesIndex) {
            return $asset->attributesIndex;
        }

        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)
            ->getCachedAssetAttributes($asset->getId());

        $index = $this->buildIndex($attributes);
        $this->resolveFallbacks($asset, $index);

        if ($applyPermissions) {
            foreach ($index->getDefinitions() as $definitionIndex) {
                $definition = $definitionIndex->getDefinition();
                if (!$this->canViewDefinitionAttributes($definition)) {
                    $index->removeDefinition($definition->getId());
                }
            }

            $asset->attributesIndex = $index;
        }

        return $index;
    }

    /**
     * The decision only depends on the definition policy and the current user,
     * so it is taken once per definition and request instead of once per asset.
     */
    private function canViewDefinitionAttributes(AttributeDefinition $definition): bool
    {
        $key = $definition->getId().'_'.($this->security->getUser()?->getUserIdentifier() ?? '_anon');

        return $this->definitionPermissionCache->get(
            $key,
            fn (): bool => $this->security->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition)
        );
    }

    /**
     * @param Attribute[] $attributes
     */
    public function buildIndex(array $attributes): AttributeIndex
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
            $fallbacks = $definition->getFallback();
            if (null === $fallbacks) {
                continue;
            }

            // The resolver skips disabled definitions, empty templates and definitions
            // already holding a value, and indexes the attributes it creates itself.
            foreach (array_keys($fallbacks) as $locale) {
                try {
                    $this->fallbackResolver->resolveAttrFallback(
                        $asset,
                        (string) $locale,
                        $definition,
                        $attributes
                    );
                } catch (\Throwable $e) {
                    if ($e instanceof UserNotifyableException) {
                        $this->exceptionNotifier->notifyException($e);
                        continue;
                    }

                    throw $e;
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
