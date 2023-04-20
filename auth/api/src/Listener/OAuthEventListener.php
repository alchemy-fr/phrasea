<?php

declare(strict_types=1);

namespace App\Listener;

use FOS\OAuthServerBundle\Event\PreAuthorizationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OAuthEventListener implements EventSubscriberInterface
{
    public function onPreAuthorizationProcess(PreAuthorizationEvent $event)
    {
        $event->setAuthorizedClient(true);
    }

    public static function getSubscribedEvents()
    {
        return [
            PreAuthorizationEvent::class => 'onPreAuthorizationProcess',
        ];
    }
}
