<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class PasswordTokenProvider implements AuthenticationProviderInterface
{
    public function authenticate(TokenInterface $token)
    {
        return $token;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof PasswordToken;
    }
}
