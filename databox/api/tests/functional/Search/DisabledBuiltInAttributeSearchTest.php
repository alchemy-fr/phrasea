<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Elasticsearch\BuiltInAttribute\OwnerBuiltInAttribute;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A built-in attribute disabled for the user (@owner when API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE
 * is set and the user lacks that role) does not exist for them: no facet, no group, no condition.
 */
class DisabledBuiltInAttributeSearchTest extends AbstractSearchTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string ROLE_ENV = 'API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE';

    /**
     * @var array{0: mixed, 1: mixed}
     */
    private array $previousRoleEnv = [null, null];

    protected function setUp(): void
    {
        $this->previousRoleEnv = [$_ENV[self::ROLE_ENV] ?? null, $_SERVER[self::ROLE_ENV] ?? null];
        // Read by the containers booted after it is set: only ADMIN has the "admin" role
        $_ENV[self::ROLE_ENV] = $_SERVER[self::ROLE_ENV] = 'admin';
        self::ensureKernelShutdown();
        // bootKernel() reuses a kernel, even shut down
        static::$kernel = null;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        [$env, $server] = $this->previousRoleEnv;
        if (null === $env) {
            unset($_ENV[self::ROLE_ENV]);
        } else {
            $_ENV[self::ROLE_ENV] = $env;
        }
        if (null === $server) {
            unset($_SERVER[self::ROLE_ENV]);
        } else {
            $_SERVER[self::ROLE_ENV] = $server;
        }

        parent::tearDown();
    }

    public function testOwnerDoesNotExistForAUserWithoutTheRole(): void
    {
        $workspace = $this->createPublicWorkspace();
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Owned',
            'public' => true,
            'ownerId' => self::ADMIN,
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $ownerCondition = sprintf('%s = "%s"', OwnerBuiltInAttribute::KEY, self::ADMIN);

        $data = $this->getDataFromResponse($this->search(self::USER, []), 200);
        $this->assertArrayNotHasKey(OwnerBuiltInAttribute::KEY, $data['facets']);

        // Refused like an unknown key, so that its existence is not confirmed
        $this->assertSearchRefused(self::USER, ['group' => [OwnerBuiltInAttribute::KEY]], 'Cannot group by "@owner": it does not exist or you are not allowed to read it');
        $this->assertSearchRefused(self::USER, ['group' => ['@unknown']], 'Cannot group by "@unknown": it does not exist or you are not allowed to read it');
        $this->assertSearchRefused(self::USER, ['conditions' => [$ownerCondition]], 'Built-in field "@owner" not found');
        $this->assertSearchRefused(self::USER, ['conditions' => [sprintf('NOT (%s)', $ownerCondition)]], 'Built-in field "@owner" not found');
        $this->assertSearchRefused(self::USER, ['conditions' => ['@unknown = "x"']], 'Built-in field "@unknown" not found');

        $this->assertNotContains(OwnerBuiltInAttribute::KEY, $this->getBuiltInAttributeIds(self::USER));

        // Unchanged for a user with the role
        $data = $this->getDataFromResponse($this->search(self::ADMIN, []), 200);
        $this->assertArrayHasKey(OwnerBuiltInAttribute::KEY, $data['facets']);

        $data = $this->getDataFromResponse($this->search(self::ADMIN, ['group' => [OwnerBuiltInAttribute::KEY]]), 200);
        $this->assertSame(['Owned'], array_column($data['member'], 'name'));

        $data = $this->getDataFromResponse($this->search(self::ADMIN, ['conditions' => [$ownerCondition]]), 200);
        $this->assertSame(['Owned'], array_column($data['member'], 'name'));

        $this->assertContains(OwnerBuiltInAttribute::KEY, $this->getBuiltInAttributeIds(self::ADMIN));
    }

    /**
     * Attribute filter rules are written by an administrator: a rule on @owner applies to
     * the users who cannot use @owner themselves (it does not hide the whole workspace).
     */
    public function testAttributeFilterRuleOnOwnerAppliesToAUserWithoutTheRole(): void
    {
        $workspace = $this->createPublicWorkspace();
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Mine',
            'public' => true,
            'ownerId' => self::USER,
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Other',
            'public' => true,
            'ownerId' => self::ADMIN,
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        self::getAttributeFilterManager()->saveRule(
            $workspace,
            [self::USER],
            [],
            sprintf('%s = "%s"', OwnerBuiltInAttribute::KEY, self::USER),
        );

        $data = $this->getDataFromResponse($this->search(self::USER, []), 200);
        $this->assertSame(['Mine'], array_column($data['member'], 'name'));
    }

    private function createPublicWorkspace(): Workspace
    {
        return $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
    }

    private function assertSearchRefused(string $userId, array $query, string $detail): void
    {
        $response = $this->search($userId, $query);
        $this->assertSame(400, $response->getStatusCode(), json_encode($query));
        $this->assertSame($detail, $response->toArray(false)['detail'] ?? null, json_encode($query));
    }

    /**
     * @return string[]
     */
    private function getBuiltInAttributeIds(string $userId): array
    {
        $response = static::createClient()->request('GET', '/built-in-attributes', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
        ]);

        return array_column($this->getDataFromResponse($response, 200)['member'], 'id');
    }

    private function search(string $userId, array $query): ResponseInterface
    {
        self::getEntityManager()->clear();

        return static::createClient()->request('GET', '/assets', [
            'query' => $query,
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
        ]);
    }
}
