<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\Workspace;
use App\Repository\Core\AttributeFilterRuleRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class AttributeFilterManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeFilterRuleRepository $repository,
    ) {
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

        return $rule;
    }

    /**
     * @return array<string, string[]> AQL conditions indexed by workspace ID
     */
    public function getUserRules(?string $userId, array $groupIds): array
    {
        $rules = $this->repository->getRules($userId, $groupIds, null);
        $wsRules = [];
        foreach ($rules as $rule) {
            $wsRules[$rule->getWorkspaceId()][] = $rule->getCondition();
        }

        return $wsRules;
    }
}
