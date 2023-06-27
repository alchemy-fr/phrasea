<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Notify;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\User;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class RegisterUserToNotifierHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'register_user_to_notifier';

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

        $this->notifier->registerUser(
            $user->getId(),
            [
                'email' => $user->getEmail(),
                'locale' => $user->getLocale(),
            ]
        );
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
