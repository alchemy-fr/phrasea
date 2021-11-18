<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\SubDefinitionClass;
use App\Entity\Core\SubDefinitionRule;
use App\Repository\Core\SubDefinitionRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubDefinitionPermissionManager
{
    private EntityManagerInterface $em;
    private array $cache = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function isGranted(Asset $asset, SubDefinitionClass $class, ?string $userId, array $groupIds = []): bool
    {
        $key = sprintf('%s:%s:%s', $userId ?? 'anon.', $asset->getId(), $class->getId());
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $ruleSets = $this->getAssetRuleSets($asset, $userId, $groupIds);

        if (null !== $ruleSets) {
            foreach ($ruleSets as $ruleSet) {
                if ($ruleSet->getAllowed()->contains($class)) {
                    return $this->cache[$key] = true;
                }
            }
        }

        return $this->cache[$key] = false;
    }

    /**
     * @param Asset       $asset
     * @param string|null $userId
     * @param array       $groupIds
     *
     * @return SubDefinitionRule[]|null
     */
    public function getAssetRuleSets(Asset $asset, ?string $userId, array $groupIds = []): ?array
    {
        /** @var SubDefinitionRuleRepository $repo */
        $repo = $this->em->getRepository(SubDefinitionRule::class);

        /** @var Collection $container */
        $container = $asset->getReferenceCollection();
        while ($container instanceof Collection) {
            $rules = $repo->getRules($userId, $groupIds, SubDefinitionRule::TYPE_COLLECTION, $container->getId());

            if (!empty($rules)) {
                return $rules;
            }

            $container = $container->getParent();
        }

        $rules = $repo->getRules($userId, $groupIds, SubDefinitionRule::TYPE_WORKSPACE, $asset->getWorkspace()->getId());

        if (!empty($rules)) {
            return $rules;
        }

        return null;
    }
}
