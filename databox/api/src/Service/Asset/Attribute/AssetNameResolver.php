<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetNameAttribute;
use App\Entity\Core\Attribute;
use App\Model\AssetTypeEnum;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class AssetNameResolver
{
    private CacheInterface $cache;

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private AttributesResolver $attributesResolver,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    public function resolveName(Asset $asset, AttributeIndex $attributesIndex, array $preferredLocales): Attribute|string|null
    {
        $target = $asset->isStory() ? AssetTypeEnum::Story : AssetTypeEnum::Asset;
        if (empty($asset->getName()) || $this->hasNameOverride($asset->getWorkspaceId())) {
            $nameAttributes = $this->getNameAttributes($asset->getWorkspaceId());
            foreach ($nameAttributes as $nameAttribute) {
                if (!$nameAttribute->isForTarget($target)) {
                    continue;
                }

                $attributeDefinition = $nameAttribute->getDefinition();
                if ($attributeDefinition->isMultiple()) {
                    $this->logger->warning(sprintf('Cannot use multiple attribute definition "%s" as name', $attributeDefinition->getId()));
                    continue;
                }
                $definitionId = $attributeDefinition->getId();

                foreach ($preferredLocales as $l) {
                    if (null !== $attribute = $attributesIndex->getAttribute($definitionId, $l)) {
                        return $attribute;
                    }
                }
            }
        }

        if (null !== $name = $asset->getName()) {
            return $name;
        }

        return $asset->getSource()?->getOriginalName();
    }

    public function resolveNameWithoutIndex(Asset $asset, array $preferredLocales): Attribute|string|null
    {
        return $this->resolveName($asset, $this->attributesResolver->resolveAssetAttributes($asset, true), $preferredLocales);
    }

    public function hasNameOverride(string $workspaceId): bool
    {
        $nameAttributes = $this->getNameAttributes($workspaceId);
        foreach ($nameAttributes as $nameAttribute) {
            if ($nameAttribute->isOverrides()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return AssetNameAttribute[]
     */
    private function getNameAttributes(string $workspaceId): array
    {
        return $this->cache->get($workspaceId, function () use ($workspaceId): array {
            return $this->em
                ->getRepository(AssetNameAttribute::class)
                ->findBy([
                    'workspace' => $workspaceId,
                ], [
                    'priority' => 'DESC',
                ]);
        });
    }
}
