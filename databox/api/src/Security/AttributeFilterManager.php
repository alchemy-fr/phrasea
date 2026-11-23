<?php

declare(strict_types=1);

namespace App\Security;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeFilterRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

readonly class AttributeFilterManager
{
    private CacheInterface $cache;

    public function __construct(
        private EntityManagerInterface $em,
        private AttributeFilterRuleRepository $repository,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createCache();
    }

    /**
     * @param string[] $userIds
     * @param string[] $groupIds
     */
    public function saveRule(
        Workspace $workspace,
        array $userIds,
        array $groupIds,
        string $condition,
        ?AttributeFilterRule $rule = null,
    ): AttributeFilterRule {
        $rule ??= new AttributeFilterRule();
        $rule->setWorkspace($workspace);
        $rule->setCondition($condition);
        $rule->setTargets($userIds, $groupIds);

        $this->em->persist($rule);
        $this->em->flush();

        if ($this->cache instanceof CacheItemPoolInterface) {
            $this->cache->clear();
        }

        return $rule;
    }

    /**
     * Cached for the request.
     *
     * @return array<string, string[]> AQL conditions indexed by workspace ID
     */
    public function getUserRules(?string $userId, array $groupIds): array
    {
        $groupIds = array_values(array_unique($groupIds));
        sort($groupIds);

        return $this->cache->get('r_'.md5(json_encode([$userId, $groupIds], JSON_THROW_ON_ERROR)), function () use ($userId, $groupIds): array {
            $wsRules = [];
            foreach ($this->repository->getRules($userId, $groupIds, null) as $rule) {
                $wsRules[$rule->getWorkspaceId()][] = $rule->getCondition();
            }

            return $wsRules;
        });
    }

    /**
     * The conditions of every rule of the workspace, whoever it targets.
     *
     * @return string[] AQL conditions
     */
    public function getWorkspaceConditions(string $workspaceId): array
    {
        return array_map(
            static fn (AttributeFilterRule $rule): string => (string) $rule->getCondition(),
            $this->repository->findBy(['workspace' => $workspaceId]),
        );
    }
}
