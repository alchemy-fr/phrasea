<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Doctrine\Listener;

use Alchemy\OAuthServerBundle\Entity\AccessToken;
use Alchemy\OAuthServerBundle\Listener\OAuth\Events;
use Alchemy\OAuthServerBundle\Listener\OAuth\OAuthEvent;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccessTokenListener
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    #[ORM\PostPersist]
    public function postPersist(AccessToken $accessToken)
    {
        $user = $accessToken->getUser();
        $event = new OAuthEvent($user);
        $this->eventDispatcher->dispatch($event, Events::ON_ACCESS_TOKEN_DELIVERED);
    }
}
