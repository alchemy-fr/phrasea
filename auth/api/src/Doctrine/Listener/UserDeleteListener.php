<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Consumer\Handler\Notify\DeleteNotifyUserHandler;
use App\Entity\User;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\Mapping as ORM;

class UserDeleteListener
{
    private array $deletedUsers = [];

    public function __construct(private readonly EventProducer $eventProducer)
    {
    }

    #[ORM\PreRemove]
    public function preRemove(User $user)
    {
        $this->deletedUsers[] = (string) $user->getId();
    }

    #[ORM\PostRemove]
    public function postRemove()
    {
        while ($userId = array_shift($this->deletedUsers)) {
            $this->eventProducer->publish(new EventMessage(DeleteNotifyUserHandler::EVENT, [
                'id' => $userId,
            ]));
        }
    }
}
