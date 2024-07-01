<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Asset\Attribute\Index\AttributeIndex;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetTitleAttribute;
use App\Entity\Core\Attribute;
use Doctrine\ORM\EntityManagerInterface;

class AssetTitleResolver
{
    private array $cache = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function resolveTitle(Asset $asset, AttributeIndex $attributesIndex, array $preferredLocales): Attribute|string|null
    {
        if (empty($asset->getTitle()) || $this->hasTitleOverride($asset->getWorkspaceId())) {
            $titleAttrs = $this->getTitleAttributes($asset->getWorkspaceId());
            foreach ($titleAttrs as $attrTitle) {
                $definitionId = $attrTitle->getDefinition()->getId();

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
        if (!isset($this->cache[$workspaceId])) {
            $this->cache[$workspaceId] = $this->em
                ->getRepository(AssetTitleAttribute::class)
                ->findBy([
                    'workspace' => $workspaceId,
                ], [
                    'priority' => 'DESC',
                ]);
        }

        return $this->cache[$workspaceId];
    }
}
