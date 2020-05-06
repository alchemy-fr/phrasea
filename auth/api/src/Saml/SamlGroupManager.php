<?php

declare(strict_types=1);

namespace App\Saml;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;

class SamlGroupManager
{
    private EntityManagerInterface $em;
    private array $groupAttributesName;
    private array $groupMap;

    public function __construct(EntityManagerInterface $em, array $groupAttributesName, array $groupMap)
    {
        $this->em = $em;
        $this->groupAttributesName = $groupAttributesName;
        $this->groupMap = $groupMap;
    }

    public function updateGroups(User $user, SamlTokenInterface $token)
    {
        $attributes = $token->getAttributes();

        $groupAttributeName = $this->groupAttributesName[$token->getIdpName()] ?? null;
        $user->getGroups()->clear();
        if ($groupAttributeName && isset($attributes[$groupAttributeName])) {
            foreach ($attributes[$groupAttributeName] as $groupName) {
                $user->addGroup($this->getOrCreateGroup($this->resolveGroupName($groupName)));
            }
        }

        $this->em->persist($user);
    }

    private function resolveGroupName(string $samlGroup): string
    {
        return $this->groupMap[$samlGroup] ?? $samlGroup;
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
