<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Listener;

use Alchemy\AdminBundle\Auth\OAuthClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutListener implements EventSubscriberInterface
{

    private OAuthClient $client;

    public function __construct(OAuthClient $client)
    {
        $this->client = $client;
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        $redirectUri = $request->headers->get('referer', $request->getUriForPath('/'));

        $response = new RedirectResponse($this->client->getLogoutUrl().'?r='.urlencode($redirectUri));

        $event->setResponse($response);
    }
}
