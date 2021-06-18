<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\OAuthServerBundle\Listener\OAuth\Events;
use Alchemy\OAuthServerBundle\Listener\OAuth\OAuthEvent;
use Alchemy\ReportBundle\ReportUserService;
use App\Entity\User;
use App\Report\AuthLogActionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationListener implements EventSubscriberInterface
{
    private ReportUserService $reportUser;
    private RequestStack $requestStack;

    public function __construct(
        ReportUserService $reportUser,
        RequestStack $requestStack
    ) {
        $this->reportUser = $reportUser;
        $this->requestStack = $requestStack;
    }

    public function onAccessToken(OAuthEvent $event)
    {
        $user = $event->getUser();

        if ($user instanceof User) {
            $this->reportUser->pushHttpRequestLog(
                $this->requestStack->getCurrentRequest(),
                AuthLogActionInterface::USER_AUTHENTICATION,
                $user->getId(), [
                    'username' => $user->getUsername(),
                ]
            );
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::ON_ACCESS_TOKEN_DELIVERED => 'onAccessToken',
        ];
    }
}
