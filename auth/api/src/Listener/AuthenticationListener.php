<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\ReportBundle\ReportUserService;
use Alchemy\ReportSDK\LogActionInterface;
use App\Entity\User;
use App\Listener\OAuth\Events;
use App\Listener\OAuth\OAuthEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationListener implements EventSubscriberInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ReportUserService
     */
    private $reportUser;

    /**
     * @var RequestStack
     */
    private $requestStack;

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
