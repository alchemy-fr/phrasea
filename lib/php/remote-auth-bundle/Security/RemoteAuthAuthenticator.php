<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class RemoteAuthAuthenticator
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function authenticateUser(
        Request $request,
        string $accessToken,
        array $tokenInfo,
        RemoteUser $user,
        string $providerKey
    ): void {
        $securityToken = new RemoteAuthToken($accessToken, $user->getRoles());
        $securityToken->setScopes($tokenInfo['scopes'] ?? []);
        $securityToken->setUser($user);

        $this->tokenStorage->setToken($securityToken);

        $this->requestStack->getSession()->set('_security_'.$providerKey, serialize($securityToken));

        $event = new InteractiveLoginEvent($request, $securityToken);
        $this->eventDispatcher->dispatch($event);
    }
}
