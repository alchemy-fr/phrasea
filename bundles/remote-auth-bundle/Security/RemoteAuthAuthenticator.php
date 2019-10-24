<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class RemoteAuthAuthenticator
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->tokenStorage = $tokenStorage;
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function authenticateUser(
        Request $request,
        string  $accessToken,
        array $tokenInfo,
        RemoteUser $user,
        string $providerKey
    ): void
    {
        $securityToken = new RemoteAuthToken($accessToken, $user->getRoles());
        $securityToken->setScopes($tokenInfo['scopes']);
        $securityToken->setAuthenticated(true);
        $securityToken->setUser($user);

        $this->tokenStorage->setToken($securityToken);

        $this->session->set('_security_'.$providerKey, serialize($securityToken));

        $event = new InteractiveLoginEvent($request, $securityToken);
        $this->eventDispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $event);
    }
}
