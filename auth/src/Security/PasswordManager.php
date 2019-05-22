<?php

namespace App\Security;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class PasswordManager
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

    public function requestPasswordResetForLogin(string $username): void
    {
        try {
            $user = $this->userManager->loadUserByUsername($username);
        } catch (UsernameNotFoundException $e) {
            return;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(128));
        $request = new ResetPasswordRequest($user, $token);

        $this->em->persist($request);
        $this->em->flush();

        // TODO send email
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPlainPassword($plainPassword);
        $this->userManager->encodePassword($user);
        $this->userManager->persistUser($user);

        // TODO send email to notice user that the password has changed
    }
}
