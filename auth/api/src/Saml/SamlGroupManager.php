<?php

declare(strict_types=1);

namespace App\Saml;

use App\Entity\Group;
use App\Entity\User;
use App\User\GroupMapper;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;

class SamlGroupManager
{
    private EntityManagerInterface $em;
    private GroupMapper $groupMapper;
    private array $groupAttributesName;

    public function __construct(EntityManagerInterface $em, GroupMapper $groupMapper, array $groupAttributesName)
    {
        $this->em = $em;
        $this->groupMapper = $groupMapper;
        $this->groupAttributesName = $groupAttributesName;
    }

    public function updateGroups(string $providerName, User $user, SamlTokenInterface $token)
    {
        $attributes = $token->getAttributes();

        $groupAttributeName = $this->groupAttributesName[$token->getIdpName()] ?? null;
        if ($groupAttributeName && isset($attributes[$groupAttributeName])) {
            $this->groupMapper->updateGroups($providerName, $user, $attributes[$groupAttributeName]);
        }
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
