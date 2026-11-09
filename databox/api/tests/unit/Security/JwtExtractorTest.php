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

    public function testKeycloakClientRolesAreMapped(): void
    {
        $user = $this->getUser(KeycloakClientTestMock::USER_UID, ['realm-management' => ['roles' => ['view-clients']]]);
        $this->assertContains(JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS, $user->getRoles());
        $this->assertNotContains(JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS, $user->getRoles());

        // Managing the clients implies seeing them
        $user = $this->getUser(KeycloakClientTestMock::USER_UID, ['realm-management' => ['roles' => ['manage-clients']]]);
        $this->assertContains(JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS, $user->getRoles());
        $this->assertContains(JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS, $user->getRoles());
    }

    public function testExpandedRealmAdminGrantsTheClientRoles(): void
    {
        // Keycloak expands the composite "realm-admin" role in the token
        $user = $this->getUser(KeycloakClientTestMock::USER_UID, ['realm-management' => ['roles' => [
            'realm-admin',
            'view-realm',
            'view-users',
            'view-clients',
            'manage-users',
            'manage-clients',
            'impersonation',
        ]]]);

        $this->assertContains(JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS, $user->getRoles());
        $this->assertContains(JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS, $user->getRoles());
        $this->assertContains(JwtUser::ROLE_IMPERSONATOR, $user->getRoles());
    }

    public function testClientRolesOfAnotherClientAreIgnored(): void
    {
        $user = $this->getUser(KeycloakClientTestMock::USER_UID, ['databox-app' => ['roles' => ['view-clients', 'manage-clients']]]);

        $this->assertNotContains(JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS, $user->getRoles());
        $this->assertNotContains(JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS, $user->getRoles());
    }

    public function testUserWithoutKeycloakClientRoles(): void
    {
        $user = $this->getUser(KeycloakClientTestMock::USER_UID);

        $this->assertNotContains(JwtUser::ROLE_KEYCLOAK_VIEW_CLIENTS, $user->getRoles());
        $this->assertNotContains(JwtUser::ROLE_KEYCLOAK_MANAGE_CLIENTS, $user->getRoles());
    }

    private function getUser(string $userId, ?array $resourceAccess = null): JwtUser
    {
        $extractor = new JwtExtractor(new RoleMapper('databox'), [], new NullLogger());
        $user = $extractor->getUserFromToken($extractor->parseJwt(KeycloakClientTestMock::getJwtFor($userId, resourceAccess: $resourceAccess)));
        $this->assertInstanceOf(JwtUser::class, $user);

        return $user;
    }
}
