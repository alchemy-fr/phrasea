<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Lcobucci\JWT\Token as TokenInterface;

interface JwtValidatorInterface
{
    public function isTokenValid(TokenInterface $token): bool;
}
