<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\OAuthServerBundle\Listener\OAuth\Events;
use Alchemy\OAuthServerBundle\Listener\OAuth\OAuthEvent;
use Alchemy\ReportBundle\ReportUserService;
use Alchemy\ReportSDK\LogActionInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private ReportUserService $reportUser;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $em,
        ReportUserService $reportUser,
        RequestStack $requestStack
    ) {
        $this->em = $em;
        $this->reportUser = $reportUser;
        $this->requestStack = $requestStack;
    }

    public function onAccessToken(OAuthEvent $event)
    {
        $user = $event->getUser();

        if ($user instanceof User) {
            $this->reportUser->pushHttpRequestLog(
                $this->requestStack->getCurrentRequest(),
                LogActionInterface::USER_AUTHENTICATION,
                $user->getId()
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
