<?php

declare(strict_types=1);

namespace App\Security\Firewall;

use Alchemy\RemoteAuthBundle\Security\RequestHelper;
use App\Security\Authentication\PasswordToken;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class PasswordTokenListener
{
    protected $tokenStorage;
    protected $authenticationManager;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
    }

    public function __invoke(RequestEvent $event)
    {
        $request = $event->getRequest();

        $password = RequestHelper::getAuthorizationFromRequest($request, 'Password', false, 'password');
        if (null === $password) {
            return;
        }

        $token = new PasswordToken($password);
        $token->setUser('password');
        $token->setAuthenticated(true);

        try {
            $authToken = $this->authenticationManager->authenticate($token);
            $this->tokenStorage->setToken($authToken);

            return;
        } catch (AuthenticationException $failed) {
            return;
        }
    }
}
