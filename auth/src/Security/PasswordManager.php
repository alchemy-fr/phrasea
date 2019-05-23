<?php

namespace App\Security;

use App\Entity\AccessToken;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Mail\Mailer;
use App\User\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Model\AccessTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    /**
     * @var AccessTokenManagerInterface
     */
    private $accessTokenManager;
    /**
     * @var Mailer
     */
    private $mailer;

    public function __construct(
        EntityManagerInterface $em,
        UserManager $userManager,
        AccessTokenManagerInterface $accessTokenManager,
    Mailer $mailer
    )
    {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->accessTokenManager = $accessTokenManager;
        $this->mailer = $mailer;
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

        // TODO defer email

        $this->mailer->send($user->getEmail(), 'Reset password', 'mail/reset_password.html.twig', [
            'url' => $token,
        ]);
    }

    public function changePassword(User $user, string $oldPassword, string $newPassword): void
    {
        if (!$this->userManager->isPasswordValid($user, $oldPassword)) {
            throw new BadRequestHttpException('Invalid old password');
        }

        $user->setPlainPassword($newPassword);
        $this->userManager->encodePassword($user);
        $this->userManager->persistUser($user);

        // TODO defer revoke tokens in a consumer
        $this
            ->em
            ->getRepository(AccessToken::class)
            ->revokeTokens($user);

        // TODO send email to notice user that the password has changed
    }
}
