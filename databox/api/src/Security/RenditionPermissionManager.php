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
    private const string ANONYMOUS = '~';

    /**
     * @var array{string, bool}
     */
    private array $cache = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function isGranted(Asset $asset, RenditionClass $class, ?string $userId, array $groupIds = []): bool
    {
        if ($class->isPublic()) {
            return true;
        }

        $assetKey = sprintf('%s:%s:%s', $asset->getId(), $class->getId(), $userId ?? self::ANONYMOUS);
        if (isset($this->cache[$assetKey])) {
            return $this->cache[$assetKey];
        }

        /** @var RenditionRuleRepository $repo */
        $repo = $this->em->getRepository(RenditionRule::class);

        if ($asset->getReferenceCollection()) {
            $result = $this->isCollectionGranted($asset->getReferenceCollection(), $class, $userId, $groupIds);
            if (null !== $result) {
                return $this->cache[$assetKey] = $result;
            }
        }

        $workspaceKey = sprintf('%s:%s:%s', $asset->getWorkspace()->getId(), $class->getId(), $userId ?? self::ANONYMOUS);
        if (isset($this->cache[$workspaceKey])) {
            return $this->cache[$assetKey] = $this->cache[$workspaceKey];
        }

        $rules = $repo->getRules($userId, $groupIds, RenditionRule::TYPE_WORKSPACE, $asset->getWorkspace()->getId());

        $result = false;
        if (!empty($rules)) {
            $result = $this->satisfyOneRule($rules, $class);
        }
        $this->cache[$workspaceKey] = $result;
        $this->cache[$assetKey] = $result;

        return $result;
    }

    private function isCollectionGranted(Collection $collection, $class, ?string $userId, array $groupIds): ?bool
    {
        /** @var RenditionRuleRepository $repo */
        $repo = $this->em->getRepository(RenditionRule::class);

        $collectionKey = sprintf('%s:%s:%s', $collection->getId(), $class->getId(), $userId ?? self::ANONYMOUS);
        if (isset($this->cache[$collectionKey])) {
            if (self::IS_EMPTY !== $this->cache[$collectionKey]) {
                return $this->cache[$collectionKey];
            }
        } else {
            $rules = $repo->getRules($userId, $groupIds, RenditionRule::TYPE_COLLECTION, $collection->getId());
            if (!empty($rules)) {
                $result = $this->satisfyOneRule($rules, $class);

                $this->cache[$collectionKey] = $result;

                return $result;
            }

            $this->cache[$collectionKey] = self::IS_EMPTY;
        }

        if (null !== $collection->getParent()) {
            $r = $this->isCollectionGranted($collection->getParent(), $class, $userId, $groupIds);
            $this->cache[$collectionKey] = $r ?? self::IS_EMPTY;

            return $r;
        }

        return null;
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
