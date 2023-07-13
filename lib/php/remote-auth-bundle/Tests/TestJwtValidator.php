<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Tests;

use Alchemy\RemoteAuthBundle\Security\JwtValidatorInterface;
use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

final class TestJwtValidator implements JwtValidatorInterface
{
    public function __construct(private readonly JwtValidatorInterface $inner)
    {
    }

    public function isTokenValid(string $token): bool
    {
        if (in_array($token, [
            AuthServiceClientTestMock::ADMIN_TOKEN,
            AuthServiceClientTestMock::USER_TOKEN,
        ])) {
            return true;
        }

        return $this->inner->isTokenValid($token);
    }
}
