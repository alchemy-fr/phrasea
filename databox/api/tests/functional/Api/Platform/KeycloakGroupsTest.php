<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Security\Impersonator;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The Keycloak groups managed from the clients (/permissions/groups), sent to Keycloak with the
 * token of the user.
 */
final class KeycloakGroupsTest extends AbstractDataboxTestCase
{
    private const string GROUP_ID = KeycloakClientTestMock::GROUP_UID;
    private const string MEMBERS_PATH = '/admin/realms/phrasea/groups/'.self::GROUP_ID.'/members';

    protected function setUp(): void
    {
        parent::setUp();
        KeycloakClientTestMock::resetGroups();
        unset(KeycloakClientTestMock::$lastQueries[self::MEMBERS_PATH]);
    }

    protected function tearDown(): void
    {
        KeycloakClientTestMock::resetGroups();
        parent::tearDown();
    }

    private function send(string $method, string $path, string $jwt, ?array $json = null, array $headers = []): ResponseInterface
    {
        return static::createClient()->request($method, $path, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$jwt,
                ...$headers,
            ],
            ...(null !== $json ? ['json' => $json] : []),
        ]);
    }

    /**
     * A user who is not an admin of the application, holding "manage-users" (as the "group-admin" role grants it).
     */
    private static function groupAdminJwt(): string
    {
        return KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID, resourceAccess: [
            'realm-management' => ['roles' => ['view-users', 'manage-users']],
        ]);
    }

    /**
     * @return list<array{string, string, array|null}>
     */
    private static function everyRoute(): array
    {
        $group = '/permissions/groups/'.self::GROUP_ID;
        $member = $group.'/members/'.KeycloakClientTestMock::OTHER_USER_UID;

        return [
            ['POST', '/permissions/groups', ['name' => 'New group']],
            ['PUT', $group, ['name' => 'Renamed']],
            ['DELETE', $group, null],
            ['GET', $group.'/members', null],
            ['PUT', $member, null],
            ['DELETE', $member, null],
        ];
    }

    private function assertEveryRouteDenied(string $jwt, array $headers = []): void
    {
        foreach (self::everyRoute() as [$method, $path, $json]) {
            $this->assertSame(403, $this->send($method, $path, $jwt, $json, $headers)->getStatusCode(), $method.' '.$path);
        }
        $this->assertSame([], KeycloakClientTestMock::$groupWrites);
        $this->assertArrayNotHasKey(self::MEMBERS_PATH, KeycloakClientTestMock::$lastQueries);
    }

    private static function getCache(): CacheInterface
    {
        return static::getContainer()->get('keycloak_realm.cache');
    }

    private static function cacheStale(string $key): void
    {
        $cache = self::getCache();
        $cache->delete($key);
        $cache->get($key, fn (): array => ['stale']);
    }

    private function assertCacheDropped(string $key): void
    {
        $cache = self::getCache();
        $value = $cache->get($key, fn (): array => ['fresh']);
        $cache->delete($key);
        $this->assertSame(['fresh'], $value, sprintf('The cache key "%s" was not dropped', $key));
    }

    public function testUserWithoutManageUsersIsDenied(): void
    {
        $this->assertEveryRouteDenied(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID));
        // Seeing the users is not enough
        $this->assertEveryRouteDenied(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID, resourceAccess: [
            'realm-management' => ['roles' => ['view-users', 'query-groups']],
        ]));
    }

    public function testAdminWithoutKeycloakRoleIsDenied(): void
    {
        // Admin of the application, but not of the Keycloak realm
        $this->assertEveryRouteDenied(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID, resourceAccess: []));
    }

    public function testImpersonatingAdminIsDenied(): void
    {
        $jwt = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID, resourceAccess: [
            'realm-management' => ['roles' => ['impersonation', 'view-users', 'manage-users']],
        ]);

        $this->assertEveryRouteDenied($jwt, [Impersonator::HEADER => KeycloakClientTestMock::OTHER_USER_UID]);
    }

    public function testCreatesATopLevelGroupWithTheTokenOfTheUser(): void
    {
        self::cacheStale('groups');
        $jwt = self::groupAdminJwt();

        $response = $this->send('POST', '/permissions/groups', $jwt, ['name' => '  Marketing  ']);

        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray();
        $this->assertSame('Marketing', $data['name']);
        $this->assertSame('/Marketing', $data['path']);
        $this->assertArrayHasKey($data['id'], KeycloakClientTestMock::$createdGroups);

        $this->assertSame([[
            'method' => 'POST',
            'path' => '/admin/realms/phrasea/groups',
            'token' => $jwt,
            'json' => ['name' => 'Marketing'],
        ]], KeycloakClientTestMock::$groupWrites);
        $this->assertCacheDropped('groups');
    }

    public function testRefusesATakenName(): void
    {
        $response = $this->send('POST', '/permissions/groups', self::groupAdminJwt(), ['name' => KeycloakClientTestMock::GROUP_NAME]);

        $this->assertSame(409, $response->getStatusCode(), $response->getContent(false));
        $data = $response->toArray(false);
        $this->assertSame('A group with this name already exists.', $data['detail']);
        $this->assertSame([[
            'propertyPath' => 'name',
            'message' => 'A group with this name already exists.',
        ]], $data['violations']);
        $this->assertSame([], KeycloakClientTestMock::$createdGroups);
    }

    public function testRefusesAnInvalidName(): void
    {
        foreach ([['name' => ''], ['name' => '   '], ['name' => str_repeat('a', 256)], ['name' => 42], []] as $json) {
            $response = $this->send('POST', '/permissions/groups', self::groupAdminJwt(), $json);

            $this->assertSame(400, $response->getStatusCode(), json_encode($json, JSON_THROW_ON_ERROR));
            $this->assertSame('name', $response->toArray(false)['violations'][0]['propertyPath']);

            $response = $this->send('PUT', '/permissions/groups/'.self::GROUP_ID, self::groupAdminJwt(), $json);
            $this->assertSame(400, $response->getStatusCode(), json_encode($json, JSON_THROW_ON_ERROR));
        }
        $this->assertSame([], KeycloakClientTestMock::$groupWrites);

        // 255 characters are allowed
        $response = $this->send('POST', '/permissions/groups', self::groupAdminJwt(), ['name' => str_repeat('é', 255)]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
    }

    public function testRenamesAGroup(): void
    {
        self::cacheStale('groups');
        self::cacheStale('groups_'.self::GROUP_ID);
        $jwt = self::groupAdminJwt();

        $response = $this->send('PUT', '/permissions/groups/'.self::GROUP_ID, $jwt, ['name' => ' Renamed ']);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([
            'id' => self::GROUP_ID,
            'name' => 'Renamed',
            'path' => '/Renamed',
        ], $response->toArray());
        $this->assertSame([[
            'method' => 'PUT',
            'path' => '/admin/realms/phrasea/groups/'.self::GROUP_ID,
            'token' => $jwt,
            'json' => ['name' => 'Renamed'],
        ]], KeycloakClientTestMock::$groupWrites);
        $this->assertCacheDropped('groups');
        $this->assertCacheDropped('groups_'.self::GROUP_ID);
    }

    public function testRefusesToRenameToATakenName(): void
    {
        $this->assertSame(201, $this->send('POST', '/permissions/groups', self::groupAdminJwt(), ['name' => 'Other'])->getStatusCode());

        $response = $this->send('PUT', '/permissions/groups/'.self::GROUP_ID, self::groupAdminJwt(), ['name' => 'Other']);

        $this->assertSame(409, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame('name', $response->toArray(false)['violations'][0]['propertyPath']);
    }

    public function testDeletesAGroup(): void
    {
        self::cacheStale('groups_'.self::GROUP_ID);
        $jwt = self::groupAdminJwt();

        $response = $this->send('DELETE', '/permissions/groups/'.self::GROUP_ID, $jwt);

        $this->assertSame(204, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([[
            'method' => 'DELETE',
            'path' => '/admin/realms/phrasea/groups/'.self::GROUP_ID,
            'token' => $jwt,
            'json' => null,
        ]], KeycloakClientTestMock::$groupWrites);
        $this->assertCacheDropped('groups_'.self::GROUP_ID);
    }

    public function testUnknownGroupIsNotFound(): void
    {
        $group = '/permissions/groups/00000000-0000-0000-0000-000000000000';
        $jwt = self::groupAdminJwt();

        $this->assertSame(404, $this->send('PUT', $group, $jwt, ['name' => 'Renamed'])->getStatusCode());
        $this->assertSame(404, $this->send('DELETE', $group, $jwt)->getStatusCode());
        $this->assertSame(404, $this->send('PUT', $group.'/members/'.KeycloakClientTestMock::USER_UID, $jwt)->getStatusCode());
        $this->assertSame(404, $this->send('DELETE', $group.'/members/'.KeycloakClientTestMock::USER_UID, $jwt)->getStatusCode());
    }

    public function testListsAPageOfTheMembers(): void
    {
        $jwt = self::groupAdminJwt();

        $response = $this->send('GET', '/permissions/groups/'.self::GROUP_ID.'/members?offset=1&limit=1', $jwt);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame([[
            'id' => KeycloakClientTestMock::OTHER_USER_UID,
            'username' => 'other_user',
            'firstName' => null,
            'lastName' => null,
            'email' => null,
        ]], $response->toArray());
        $this->assertSame([
            'briefRepresentation' => 'true',
            'first' => 1,
            'max' => 1,
        ], KeycloakClientTestMock::$lastQueries[self::MEMBERS_PATH]);

        // Pages of 30 by default, of 100 at most
        $response = $this->send('GET', '/permissions/groups/'.self::GROUP_ID.'/members', $jwt);
        $this->assertCount(3, $response->toArray());
        $this->assertSame(30, KeycloakClientTestMock::$lastQueries[self::MEMBERS_PATH]['max']);
        $this->send('GET', '/permissions/groups/'.self::GROUP_ID.'/members?limit=1000&offset=-5', $jwt)->getStatusCode();
        $this->assertSame(100, KeycloakClientTestMock::$lastQueries[self::MEMBERS_PATH]['max']);
        $this->assertSame(0, KeycloakClientTestMock::$lastQueries[self::MEMBERS_PATH]['first']);
    }

    public function testAddsAndRemovesAMember(): void
    {
        $jwt = self::groupAdminJwt();
        $member = '/permissions/groups/'.self::GROUP_ID.'/members/'.KeycloakClientTestMock::ADMIN_UID;

        $this->assertSame(204, $this->send('PUT', $member, $jwt)->getStatusCode());
        $this->assertSame(204, $this->send('DELETE', $member, $jwt)->getStatusCode());

        $path = '/admin/realms/phrasea/users/'.KeycloakClientTestMock::ADMIN_UID.'/groups/'.self::GROUP_ID;
        $this->assertSame([
            ['method' => 'PUT', 'path' => $path, 'token' => $jwt, 'json' => null],
            ['method' => 'DELETE', 'path' => $path, 'token' => $jwt, 'json' => null],
        ], KeycloakClientTestMock::$groupWrites);
    }

    public function testInvalidIdsAreNotRouted(): void
    {
        $jwt = self::groupAdminJwt();

        $this->assertSame(404, $this->send('PUT', '/permissions/groups/a_b', $jwt, ['name' => 'Renamed'])->getStatusCode());
        $this->assertSame(404, $this->send('DELETE', '/permissions/groups/'.str_repeat('a', 65), $jwt)->getStatusCode());
        $this->assertSame(404, $this->send('PUT', '/permissions/groups/'.self::GROUP_ID.'/members/a.b', $jwt)->getStatusCode());
        $this->assertSame([], KeycloakClientTestMock::$groupWrites);
    }
}
