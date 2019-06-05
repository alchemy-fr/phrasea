<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\ResetPasswordRequest;
use App\Mail\Mailer;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class RequestResetPasswordHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'request_reset_password';
    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var UserManager
     */
    private $userManager;

    public function __construct(UserManager $userManager, Mailer $mailer)
    {
        $this->mailer = $mailer;
        $this->userManager = $userManager;
    }

    public function handle(EventMessage $message): void
    {
        $username = $message->getPayload()['username'];

        try {
            $user = $this->userManager->loadUserByUsername($username);
        } catch (UsernameNotFoundException $e) {
            $this->logger->notice(sprintf('Request reset password: Username "%s" not found', $username));

            return;
        }

        $em = $this->getEntityManager();
        $lastUserRequest = $em->getRepository(ResetPasswordRequest::class)->findLastUserRequest($user);
        if (null !== $lastUserRequest && !$lastUserRequest->hasExpired()) {
            $this->logger->notice(sprintf(
                'Request reset password: already requested for "%s" (created at: %s)',
                $username,
                $lastUserRequest->getCreatedAt()->format('Y-m-d H:i:s')
            ));

            return;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(128));
        $request = new ResetPasswordRequest($user, $token);

        $em->persist($request);
        $em->flush();

        $this->mailer->send($user->getEmail(), 'Reset password', 'mail/reset_password.html.twig', [
            'id' => $request->getId(),
            'token' => $request->getToken(),
        ]);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
