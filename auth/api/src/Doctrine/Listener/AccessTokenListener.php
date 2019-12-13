<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Listener\OAuth\Events;
use App\Listener\OAuth\OAuthEvent;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccessTokenListener
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @ORM\PostPersist()
     */
    public function postPersist(AccessToken $accessToken)
    {
        /** @var User $user */
        $user = $accessToken->getUser();
        $event = new OAuthEvent($user);
        $this->eventDispatcher->dispatch($event, Events::ON_ACCESS_TOKEN_DELIVERED);
    }
}
