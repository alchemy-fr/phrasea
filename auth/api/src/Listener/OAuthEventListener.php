<?php

declare(strict_types=1);

namespace App\Listener;

use FOS\OAuthServerBundle\Event\OAuthEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OAuthEventListener implements EventSubscriberInterface
{
    public function onPreAuthorizationProcess(OAuthEvent $event)
    {
        $event->setAuthorizedClient(true);
    }

    public static function getSubscribedEvents()
    {
        return [
            OAuthEvent::PRE_AUTHORIZATION_PROCESS => 'onPreAuthorizationProcess',
        ];
    }
}
