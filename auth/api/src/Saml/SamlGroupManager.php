<?php

declare(strict_types=1);

namespace App\Saml;

use App\Entity\Group;
use App\Entity\User;
use App\User\GroupMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SamlGroupManager
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly GroupMapper $groupMapper, private readonly array $groupAttributesName)
    {
    }

    public function updateGroups(string $providerName, User $user, TokenInterface $token)
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
