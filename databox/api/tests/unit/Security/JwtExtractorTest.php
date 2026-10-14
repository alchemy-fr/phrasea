<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use Alchemy\AuthBundle\Security\JwtExtractor;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\RoleMapper;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class JwtExtractorTest extends TestCase
{
    public function testKeycloakImpersonationRoleGrantsImpersonator(): void
    {
        $user = $this->getUser(KeycloakClientTestMock::ADMIN_UID);

        $this->assertContains(JwtUser::ROLE_IMPERSONATOR, $user->getRoles());
    }

    public function testUserWithoutKeycloakImpersonationRoleIsNotImpersonator(): void
    {
        $user = $this->getUser(KeycloakClientTestMock::USER_UID);

        $this->assertNotContains(JwtUser::ROLE_IMPERSONATOR, $user->getRoles());
    }

    private function getUser(string $userId): JwtUser
    {
        $extractor = new JwtExtractor(new RoleMapper('databox'), [], new NullLogger());
        $user = $extractor->getUserFromToken($extractor->parseJwt(KeycloakClientTestMock::getJwtFor($userId)));
        $this->assertInstanceOf(JwtUser::class, $user);

        return $user;
    }
}
