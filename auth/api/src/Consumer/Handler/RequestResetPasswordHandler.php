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
    const EVENT = 'request_reset_password';

    /**
     * @var UserManager
     */
    private $userManager;
    /**
     * @var NotifierInterface
     */
    private $notifier;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(
        UserManager $userManager,
        NotifierInterface $notifier,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->userManager = $userManager;
        $this->notifier = $notifier;
        $this->urlGenerator = $urlGenerator;
    }

    public function handle(EventMessage $message): void
    {
        $payload = $message->getPayload();
        $username = $payload['username'];
        $locale = $payload['locale'];

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

        $this->notifier->notifyUser(
            $user->getId(),
            'auth/reset_password', [
                'reset_url' => $this->urlGenerator->generate('reset_password', [
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
