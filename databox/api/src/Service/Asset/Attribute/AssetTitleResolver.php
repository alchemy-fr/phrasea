<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetTitleAttribute;
use App\Entity\Core\Attribute;
use App\Model\AssetTypeEnum;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class AssetTitleResolver
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

    public function resolveTitle(Asset $asset, AttributeIndex $attributesIndex, array $preferredLocales): Attribute|string|null
    {
        $target = $asset->isStory() ? AssetTypeEnum::Story : AssetTypeEnum::Asset;
        if (empty($asset->getTitle()) || $this->hasTitleOverride($asset->getWorkspaceId())) {
            $titleAttrs = $this->getTitleAttributes($asset->getWorkspaceId());
            foreach ($titleAttrs as $attrTitle) {
                if (!$attrTitle->isForTarget($target)) {
                    continue;
                }

                $attributeDefinition = $attrTitle->getDefinition();
                if ($attributeDefinition->isMultiple()) {
                    $this->logger->warning(sprintf('Cannot use multiple attribute definition "%s" as title', $attributeDefinition->getId()));
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

        if (null !== $title = $asset->getTitle()) {
            return $title;
        }

        return $asset->getSource()?->getOriginalName();
    }

    public function resolveTitleWithoutIndex(Asset $asset, array $preferredLocales): Attribute|string|null
    {
        return $this->resolveTitle($asset, $this->attributesResolver->resolveAssetAttributes($asset, true), $preferredLocales);
    }

    public function hasTitleOverride(string $workspaceId): bool
    {
        $titleAttrs = $this->getTitleAttributes($workspaceId);
        foreach ($titleAttrs as $attrTitle) {
            if ($attrTitle->isOverrides()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return AssetTitleAttribute[]
     */
    private function getTitleAttributes(string $workspaceId): array
    {
        return $this->cache->get($workspaceId, function () use ($workspaceId): array {
            return $this->em
                ->getRepository(AssetTitleAttribute::class)
                ->findBy([
                    'workspace' => $workspaceId,
                ], [
                    'priority' => 'DESC',
                ]);
        });
    }
}
