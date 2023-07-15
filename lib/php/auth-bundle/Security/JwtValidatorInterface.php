<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

interface JwtValidatorInterface
{
    public function isTokenValid(string $token): bool;
}
