<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionClass;
use App\Entity\Core\RenditionRule;
use App\Repository\Core\RenditionRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

class RenditionPermissionManager
{
    private const IS_EMPTY = 0;

    private EntityManagerInterface $em;
    private array $cache = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function isGranted(Asset $asset, RenditionClass $class, ?string $userId, array $groupIds = []): bool
    {
        $assetKey = sprintf('%s:%s:%s', $asset->getId(), $class->getId(), $userId ?? 'anon.');
        if (isset($this->cache[$assetKey])) {
            return $this->cache[$assetKey];
        }

        /** @var RenditionRuleRepository $repo */
        $repo = $this->em->getRepository(RenditionRule::class);

        /** @var Collection $container */
        $container = $asset->getReferenceCollection();
        while ($container instanceof Collection) {
            $collectionKey = sprintf('%s:%s:%s', $container->getId(), $class->getId(), $userId ?? 'anon.');
            if (isset($this->cache[$collectionKey])) {
                if ($this->cache[$collectionKey] !== self::IS_EMPTY) {
                    return $this->cache[$assetKey] = $this->cache[$collectionKey];
                }
            } else {
                $rules = $repo->getRules($userId, $groupIds, RenditionRule::TYPE_COLLECTION, $container->getId());
                if (!empty($rules)) {
                    if ($this->satisfyOneRule($rules, $class)) {
                        $this->cache[$collectionKey] = true;
                        $this->cache[$assetKey] = true;

                        return true;
                    }

                    $this->cache[$collectionKey] = false;
                    $this->cache[$assetKey] = false;

                    return false;
                } else {
                    $this->cache[$collectionKey] = self::IS_EMPTY;
                }
            }

            $container = $container->getParent();
        }

        $workspaceKey = sprintf('%s:%s:%s', $asset->getWorkspace()->getId(), $class->getId(), $userId ?? 'anon.');
        if (isset($this->cache[$workspaceKey])) {
            return $this->cache[$assetKey] = $this->cache[$workspaceKey];
        }

        $rules = $repo->getRules($userId, $groupIds, RenditionRule::TYPE_WORKSPACE, $asset->getWorkspace()->getId());
        if (!empty($rules)) {
            if ($this->satisfyOneRule($rules, $class)) {
                $this->cache[$workspaceKey] = true;
                $this->cache[$assetKey] = true;

                return true;
            }
        }
        $this->cache[$workspaceKey] = false;
        $this->cache[$assetKey] = false;

        return false;
    }

    private function satisfyOneRule(array $ruleSets, RenditionClass $class): bool
    {
        foreach ($ruleSets as $ruleSet) {
            if ($ruleSet->getAllowed()->contains($class)) {
                return true;
            }
        }

        return false;
    }
}
