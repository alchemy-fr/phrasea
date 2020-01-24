<?php

declare(strict_types=1);

namespace App\Saml;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;

class SamlGroupManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var array
     */
    private $groupAttributesName;

    public function __construct(EntityManagerInterface $em, array $groupAttributesName)
    {
        $this->em = $em;
        $this->groupAttributesName = $groupAttributesName;
    }

    public function updateGroups(User $user, SamlTokenInterface $token)
    {
        $attributes = $token->getAttributes();

        $groupAttributeName = $this->groupAttributesName[$token->getIdpName()] ?? null;
        $user->getGroups()->clear();
        if ($groupAttributeName && isset($attributes[$groupAttributeName])) {
            foreach ($attributes[$groupAttributeName] as $groupName) {
                $user->addGroup($this->getOrCreateGroup($groupName));
            }
        }

        $this->em->persist($user);
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
