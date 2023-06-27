<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class GroupMapper
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly array $groupMaps)
    {
    }

    public function updateGroups(string $providerName, User $user, array $groups): void
    {
        // Some hierarchical groups can have a beginning slash, remove it:
        $groups = array_map(fn (string $name): string => preg_replace('#^/#', '', $name), $groups);

        $groups = array_map(fn (string $group): string => $this->resolveGroupName($providerName, $group), $groups);

        // remove old groups
        foreach ($user->getGroups() as $group) {
            if (!in_array($group, $groups, true)) {
                $user->removeGroup($group);
            }
        }

        foreach ($groups as $groupName) {
            $user->addGroup($this->getOrCreateGroup($groupName));
        }

        $this->em->persist($user);
    }

    private function resolveGroupName(string $providerName, string $group): string
    {
        return $this->groupMaps[$providerName][$group] ?? $group;
    }

    private function getOrCreateGroup(string $groupName): Group
    {
        $group = $this->em
            ->getRepository(Group::class)
            ->findOneBy(['name' => $groupName]);

        if ($group instanceof Group) {
            return $group;
        }

        $group = new Group();
        $group->setName($groupName);

        $this->em->persist($group);

        return $group;
    }
}
