<?php

namespace App\Saml;

use App\Entity\SamlIdentity;
use App\Entity\User;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Security\Authentication\Token\SamlTokenInterface;
use Hslavich\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;

class SamlUserFactory implements SamlUserFactoryInterface
{
    private EntityManagerInterface $em;
    private UserManager $userManager;
    private SamlGroupManager $groupManager;

    public function __construct(EntityManagerInterface $em, UserManager $userManager, SamlGroupManager $groupManager)
    {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    public function createUser(SamlTokenInterface $token): User
    {
        $attributes = $token->getAttributes();

        $samlIdentity = new SamlIdentity();
        $idpName = $token->getIdpName();
        $samlIdentity->setProvider($idpName);
        $samlIdentity->setAttributes($attributes);

        if (null === $user = $this->userManager->findUserByUsername($token->getUsername())) {
            $user = $this->userManager->createUser();
            $user->setUsername($token->getUsername());
            $user->setEnabled(true);
        }

        $this->groupManager->updateGroups($idpName, $user, $token);

        $samlIdentity->setUser($user);

        $this->em->persist($user);
        $this->em->persist($samlIdentity);
        $this->em->flush();

        return $user;
    }
}
