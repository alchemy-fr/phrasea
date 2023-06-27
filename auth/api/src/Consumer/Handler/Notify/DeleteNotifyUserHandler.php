<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Notify;

use Alchemy\NotifyBundle\Notify\NotifierInterface;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;

class DeleteNotifyUserHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'delete_notify_user';

    public function __construct(private readonly NotifierInterface $notifier)
    {
    }

    public function handle(EventMessage $message): void
    {
        $userId = $message->getPayload()['id'];
        $this->notifier->deleteUser($userId);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
