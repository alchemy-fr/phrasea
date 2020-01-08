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
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var UserManager
     */
    private $userManager;

    public function __construct(EntityManagerInterface $em, UserManager $userManager)
    {
        $this->em = $em;
        $this->userManager = $userManager;
    }

    public function createUser(SamlTokenInterface $token): User
    {
        $samlIdentity = new SamlIdentity();
        $samlIdentity->setAttributes($token->getAttributes());

        $samlIdentity->setUsername($token->getUsername());
        $user = $this->userManager->createUser();
        $user->setUsername($token->getUsername());
        $user->setEnabled(true);
        $samlIdentity->setUser($user);

        $this->em->persist($samlIdentity);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
