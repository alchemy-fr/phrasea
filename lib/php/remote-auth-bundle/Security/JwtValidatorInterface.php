<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

interface JwtValidatorInterface
{
    public function isTokenValid(string $token): bool;
}
