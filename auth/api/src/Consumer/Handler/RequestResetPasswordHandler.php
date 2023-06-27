<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\ResetPasswordRequest;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class RequestResetPasswordHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'request_reset_password';

    public function __construct(private readonly UserManager $userManager, private readonly NotifierInterface $notifier, private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $username = $payload['username'];
        $locale = $payload['locale'];

        try {
            $user = $this->userManager->loadUserByUsername($username);
        } catch (UsernameNotFoundException) {
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

        $this->notifier->notifyUser(
            $user->getId(),
            'auth/reset_password', [
                'reset_url' => $this->urlGenerator->generate('password_reset_reset', [
                    '_locale' => $locale,
                    'id' => $request->getId(),
                    'token' => $request->getToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
