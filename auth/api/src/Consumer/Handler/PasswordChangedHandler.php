<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use Alchemy\OAuthServerBundle\Entity\AccessToken;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class PasswordChangedHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'password_changed';

    public function __construct(private readonly NotifierInterface $notifier)
    {
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];

        $em = $this->getEntityManager();

        $user = $em->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new ObjectNotFoundForHandlerException(User::class, $userId, self::class);
        }

        $em
            ->getRepository(AccessToken::class)
            ->revokeTokens($user->getId());
        $em
            ->getRepository(ResetPasswordRequest::class)
            ->revokeRequests($user);

        $this->notifier->notifyUser(
            $user->getId(),
            'auth/password_changed'
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
