<?php

namespace App\Security;

use App\Consumer\Handler\PasswordChangedHandler;
use App\Consumer\Handler\RequestResetPasswordHandler;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Model\AccessTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(
        EntityManagerInterface $em,
        UserManager $userManager,
        AccessTokenManagerInterface $accessTokenManager,
        EventProducer $eventProducer
    )
    {
        $this->em = $em;
        $this->userManager = $userManager;
        $this->accessTokenManager = $accessTokenManager;
        $this->eventProducer = $eventProducer;
    }

    public function requestPasswordResetForLogin(string $username): void
    {
        $this->eventProducer->publish(new EventMessage(RequestResetPasswordHandler::EVENT, [
            'username' => $username,
        ]));
    }

    public function getResetRequest(string $requestId, string $token): ResetPasswordRequest
    {
        $request = $this->em
            ->getRepository(ResetPasswordRequest::class)
            ->findOneBy([
                'id' => $requestId,
                'token' => $token,
            ]);

        if (null === $request) {
            throw new AccessDeniedHttpException('Invalid reset request');
        }

        if ($request->hasExpired()) {
            throw new AccessDeniedHttpException('Request has expired');
        }

        return $request;
    }

    public function resetPassword(string $requestId, string $token, string $newPassword): void
    {
        $request = $this->getResetRequest($requestId, $token);
        $this->doChangePassword($request->getUser(), $newPassword);
    }

    public function changePassword(User $user, string $oldPassword, string $newPassword): void
    {
        if (!$this->userManager->isPasswordValid($user, $oldPassword)) {
            throw new BadRequestHttpException('Invalid old password');
        }

        $this->doChangePassword($user, $newPassword);
    }

    private function doChangePassword(User $user, string $newPassword): void
    {
        $user->setPlainPassword($newPassword);
        $this->userManager->encodePassword($user);
        $this->userManager->persistUser($user);

        $this->eventProducer->publish(new EventMessage(PasswordChangedHandler::EVENT, [
            'id' => $user->getId(),
        ]));
    }
}
