<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\RemoteAuthBundle\Security\Firewall\RemoteAuthListener;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use App\Security\Authentication\PasswordToken;
use App\Security\Firewall\PasswordTokenListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

class AuthorizationListener implements EventSubscriberInterface
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();

        foreach ([
                     PasswordTokenListener::COOKIE_NAME,
                     RemoteAuthListener::COOKIE_NAME
                 ] as $cookieName) {
            if ($request->cookies->has($cookieName)) {
                // Cookie is already set, pass.
                return;
            }
        }

        $response = $event->getResponse();
        $token = $this->security->getToken();

        $authCookie = null;
        if ($token instanceof PasswordToken) {
            $authCookie = Cookie::create(PasswordTokenListener::COOKIE_NAME, $token->getPassword());
        } elseif ($token instanceof RemoteAuthToken) {
            $authCookie = Cookie::create(RemoteAuthListener::COOKIE_NAME, $token->getAccessToken());
        }

        if (null !== $authCookie) {
            $response->headers->setCookie($authCookie);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
