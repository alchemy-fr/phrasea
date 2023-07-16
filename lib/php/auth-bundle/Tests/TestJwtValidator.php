<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Tests;

use Alchemy\AuthBundle\Security\JwtValidatorInterface;
use Alchemy\AuthBundle\Tests\Client\OAuthClientTestMock;

final class TestJwtValidator implements JwtValidatorInterface
{
    public function __construct(private readonly JwtValidatorInterface $inner)
    {
    }

    public function isTokenValid(string $token): bool
    {
        if (in_array($token, [
            OAuthClientTestMock::ADMIN_TOKEN,
            OAuthClientTestMock::USER_TOKEN,
        ])) {
            return true;
        }

        return $this->inner->isTokenValid($token);
    }
}
