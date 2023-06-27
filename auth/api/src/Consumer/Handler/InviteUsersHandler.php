<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;

class InviteUsersHandler extends AbstractEntityManagerHandler
{
    public const EVENT = 'invite_users';

    /**
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        $this->eventProducer = $eventProducer;
    }

    public function handle(EventMessage $message): void
    {
        $userIds = $message->getPayload()['user_ids'];

        foreach ($userIds as $id) {
            $this->eventProducer->publish(new EventMessage(UserInviteHandler::EVENT, [
                'id' => $id,
            ]));
        }
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
