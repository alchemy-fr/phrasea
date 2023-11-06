<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Listener;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $clientId,
        private readonly KeycloakUrlGenerator $urlGenerator,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $redirectUri = $request->headers->get('referer', $request->getUriForPath('/'));
        $response = new RedirectResponse($this->urlGenerator->getLogoutUrl($this->clientId, $redirectUri));

        $event->setResponse($response);
    }
}
