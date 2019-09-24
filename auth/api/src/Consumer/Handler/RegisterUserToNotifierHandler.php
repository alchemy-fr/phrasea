<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use App\Entity\User;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Consumer\Exception\ObjectNotFoundForHandlerException;

class RegisterUserToNotifierHandler extends AbstractEntityManagerHandler
{
    const EVENT = 'register_user_to_notifier';

    /**
     * @var NotifierInterface
     */
    private $notifier;

    public function __construct(NotifierInterface $notifier)
    {
        $this->notifier = $notifier;
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];

        $em = $this->getEntityManager();

        $user = $em->find(User::class, $userId);
        if (!$user instanceof User) {
            throw new ObjectNotFoundForHandlerException(User::class, $userId, __CLASS__);
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
