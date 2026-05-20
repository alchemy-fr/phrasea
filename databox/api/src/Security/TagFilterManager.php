<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Core\Tag;
use App\Entity\Core\TagFilterRule;
use App\Entity\Core\Workspace;
use App\Repository\Core\TagFilterRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

readonly class TagFilterManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private TagFilterRuleRepository $repository,
    ) {
    }

    public function updateRule(
        Workspace $workspace,
        int $userType,
        ?string $userId,
        array $include,
        array $exclude,
    ): TagFilterRule {

        $existingRules = $this->repository->findRules([
            'userType' => $userType,
            'userId' => $userId,
            'workspace' => $workspace->getId(),
        ]);
        if (!empty($existingRules)) {
            $filter = reset($existingRules);
        } else {
            $filter = new TagFilterRule();
            $filter->setUserType($userType);
            $filter->setUserId($userId);
        }

        $filter->setWorkspace($workspace);

        $filter->setInclude(array_map(fn (string $id): Tag => $this->em->getReference(Tag::class, Uuid::fromString($id)), $include));
        $filter->setExclude(array_map(fn (string $id): Tag => $this->em->getReference(Tag::class, Uuid::fromString($id)), $exclude));

        $this->em->persist($filter);
        $this->em->flush();

        return $filter;
    }

    public function getUserRules(?string $userId, array $groupIds): array
    {
        $rules = $this->repository->getRules($userId, $groupIds, null);
        $wsRules = [];
        foreach ($rules as $rule) {
            $wsRules[$rule->getWorkspaceId()][] = $rule;
        }
        foreach ($wsRules as $wsId => $rules) {
            $wsRules[$wsId] = $this->mergeRules($rules);
        }

        return $wsRules;
    }

    /**
     * @param TagFilterRule[] $rules
     */
    private function mergeRules(array $rules): array
    {
        $include = [];
        $exclude = [];

        foreach ($rules as $rule) {
            $include = [...$include, ...$rule->getInclude()->map(fn (Tag $tag): string => $tag->getId())->getValues()];
            $exclude = [...$exclude, ...$rule->getExclude()->map(fn (Tag $tag): string => $tag->getId())->getValues()];
        }

        return [
            'include' => array_values(array_unique($include)),
            'exclude' => array_values(array_unique($exclude)),
        ];
    }
}
