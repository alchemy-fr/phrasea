<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Core\Tag;
use App\Entity\Core\TagFilterRule;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class TagFilterManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function updateRule(
        int $userType,
        ?string $userId,
        int $objectType,
        string $objectId,
        array $include,
        array $exclude
    ): TagFilterRule {
        $repo = $this->em->getRepository(TagFilterRule::class);

        $existingRules = $repo->findRules([
            'userType' => $userType,
            'userId' => $userId,
            'objectType' => $objectType,
            'objectId' => $objectId,
        ]);
        if (!empty($existingRules)) {
            $filter = reset($existingRules);
        } else {
            $filter = new TagFilterRule();
            $filter->setUserType($userType);
            $filter->setUserId($userId);
        }

        $filter->setObjectType($objectType);
        $filter->setObjectId($objectId);

        $filter->setInclude(array_map(fn(string $id): Tag => $this->em->getReference(Tag::class, Uuid::fromString($id)), $include));
        $filter->setExclude(array_map(fn(string $id): Tag => $this->em->getReference(Tag::class, Uuid::fromString($id)), $exclude));

        $this->em->persist($filter);
        $this->em->flush();

        return $filter;
    }

    public function getUserRules(?string $userId, array $groupIds): array
    {
        $repo = $this->em->getRepository(TagFilterRule::class);

        /** @var TagFilterRule[] $rules */
        $rules = $repo->getRules($userId, $groupIds, TagFilterRule::TYPE_WORKSPACE, null);
        $wsRules = [];
        foreach ($rules as $rule) {
            $id = $rule->getObjectId();
            $wsRules[$id][] = $rule;
        }
        foreach ($wsRules as $wsId => $rules) {
            $wsRules[$wsId] = $this->mergeRules($rules);
        }

        $collRules = [];
        /** @var TagFilterRule[] $rules */
        $rules = $repo->getRules($userId, $groupIds, TagFilterRule::TYPE_COLLECTION, null);
        foreach ($rules as $rule) {
            $id = $rule->getObjectId();
            $collRules[$id][] = $rule;
        }

        foreach ($collRules as $collId => $rules) {
            $collRules[$collId] = $this->mergeRules($rules);
        }

        return [
            'workspaces' => $wsRules,
            'collections' => $collRules,
        ];
    }

    /**
     * @param TagFilterRule[] $rules
     */
    private function mergeRules(array $rules): array
    {
        $include = [];
        $exclude = [];

        foreach ($rules as $rule) {
            $include = [...$include, ...$rule->getInclude()->map(fn(Tag $tag): string => $tag->getId())->getValues()];
            $exclude = [...$exclude, ...$rule->getExclude()->map(fn(Tag $tag): string => $tag->getId())->getValues()];
        }

        return [
            'include' => array_values(array_unique($include)),
            'exclude' => array_values(array_unique($exclude)),
        ];
    }
}
