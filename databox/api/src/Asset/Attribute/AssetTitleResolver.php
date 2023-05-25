<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

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

    /**
     * @param array<string, array<string, Attribute>> $attributes
     */
    public function resolveTitle(Asset $asset, array $attributes, array $preferredLocales): ?Attribute
    {
        if (empty($asset->getTitle()) || $this->hasTitleOverride($asset->getWorkspaceId())) {
            $titleAttrs = $this->getTitleAttributes($asset->getWorkspaceId());
            foreach ($titleAttrs as $attrTitle) {
                foreach ($attributes as $_attrs) {
                    foreach ($preferredLocales as $l) {
                        if (isset($_attrs[$l])) {
                            $attribute = $_attrs[$l];
                            if ($attribute->getDefinition()->getId() === $attrTitle->getDefinition()->getId()) {
                                return $attribute;
                            }
                        }
                    }
                }
            }
        }

        return null;
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
