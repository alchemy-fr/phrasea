<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceEnv;
use App\Entity\Integration\WorkspaceSecret;
use App\Integration\IntegrationManager;
use App\Security\Secrets\SecretsManager;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Secrets (/workspace-secrets) and variables (/workspace-envs) referenced by the
 * configuration of the integrations: managed by the editors of their workspace
 * (by the admins for the instance ones), values of the secrets never returned.
 */
final class WorkspaceSecretApiTest extends AbstractDataboxTestCase
{
    use IntegrationTestTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    private const array SYSTEM_VARIABLES = [
        'PWI_FT_SYS_FOO' => 'system-value',
        'PWI_FT_SYS_EMPTY' => '',
    ];

    private function request(string $method, string $uri, ?string $userId, ?array $json = null): ResponseInterface
    {
        $options = ['headers' => $this->headers($userId)];
        if ('PATCH' === $method) {
            $options['headers']['Content-Type'] = 'application/merge-patch+json';
        }
        if (null !== $json) {
            $options['json'] = $json;
        }

        return static::createClient()->request($method, $uri, $options);
    }

    private function assertRequestStatus(int $expected, string $method, string $uri, ?string $userId, ?array $json = null): ResponseInterface
    {
        $response = $this->request($method, $uri, $userId, $json);
        $this->assertSame(
            $expected,
            $response->getStatusCode(),
            sprintf('%s %s as %s: unexpected status. Body: %s', $method, $uri, $userId ?? 'anonymous', $response->getContent(false))
        );

        return $response;
    }

    private function createSecret(?Workspace $workspace, string $name, string $value): WorkspaceSecret
    {
        $em = self::getEntityManager();
        $secret = new WorkspaceSecret();
        $secret->setWorkspace($workspace);
        $secret->setName($name);
        $secret->setPlainValue($value);
        $em->persist($secret);
        $em->flush();

        return $secret;
    }

    private function createEnv(?Workspace $workspace, string $name, string $value): WorkspaceEnv
    {
        $em = self::getEntityManager();
        $env = new WorkspaceEnv();
        $env->setWorkspace($workspace);
        $env->setName($name);
        $env->setValue($value);
        $em->persist($env);
        $em->flush();

        return $env;
    }

    private function findSecret(string $id): ?WorkspaceSecret
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(WorkspaceSecret::class, $id);
    }

    private function decrypt(WorkspaceSecret $secret): string
    {
        return self::getService(SecretsManager::class)->decryptSecret($secret->getValue());
    }

    public function testAWorkspaceEditorCreatesASecretWhoseValueIsNeverReturned(): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = $this->assertRequestStatus(201, 'POST', '/workspace-secrets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BG_KEY',
            'value' => 'my-plain-key',
        ]);

        $data = $response->toArray();
        $this->assertSame('BG_KEY', $data['name']);
        $this->assertSame('/workspaces/'.$workspace->getId(), $data['workspace']);
        $this->assertArrayNotHasKey('value', $data);
        $this->assertStringNotContainsString('my-plain-key', $response->getContent());

        $secret = $this->findSecret($data['id']);
        $this->assertNotNull($secret);
        $this->assertNotSame('my-plain-key', $secret->getValue());
        $this->assertSame('my-plain-key', $this->decrypt($secret));

        $item = $this->assertRequestStatus(200, 'GET', '/workspace-secrets/'.$secret->getId(), self::OWNER);
        $this->assertSame('BG_KEY', $item->toArray()['name']);
        $this->assertStringNotContainsString($secret->getValue(), $item->getContent());

        $list = $this->assertRequestStatus(200, 'GET', '/workspace-secrets?workspace='.$workspace->getId(), self::OWNER)->toArray();
        $this->assertSame(['BG_KEY'], array_column($list['hydra:member'] ?? $list['member'], 'name'));
    }

    public function testTheSecretsOfAWorkspaceAreListedByName(): void
    {
        $workspace = $this->createSharedWorkspace();
        $other = $this->createWorkspace(['ownerId' => self::OWNER, 'name' => 'Second', 'slug' => 'second']);
        $this->createSecret($workspace, 'ZETA', 'z');
        $this->createSecret($workspace, 'ALPHA', 'a');
        $this->createSecret($other, 'OTHER', 'o');
        $this->createSecret(null, 'GLOBAL', 'g');

        $list = $this->assertRequestStatus(200, 'GET', '/workspace-secrets?workspace=/workspaces/'.$workspace->getId(), self::OWNER)->toArray();
        $this->assertSame(['ALPHA', 'ZETA'], array_column($list['hydra:member'] ?? $list['member'], 'name'));
    }

    public static function getForbiddenUsers(): iterable
    {
        yield 'workspace reader' => [true];
        yield 'not a member' => [false];
    }

    #[DataProvider('getForbiddenUsers')]
    public function testTheSecretsOfAWorkspaceAreForbiddenToNonEditors(bool $reader): void
    {
        if ($reader) {
            $workspace = $this->createSharedWorkspace();
            $userId = self::MEMBER;
        } else {
            $workspace = $this->createOtherWorkspace();
            $userId = self::OWNER;
        }
        $secret = $this->createSecret($workspace, 'BG_KEY', 'my-plain-key');
        $before = $secret->getValue();
        $env = $this->createEnv($workspace, 'HOST', 'example.test');

        $this->assertRequestStatus(403, 'GET', '/workspace-secrets?workspace='.$workspace->getId(), $userId);
        $this->assertRequestStatus(403, 'GET', '/workspace-envs?workspace='.$workspace->getId(), $userId);
        $this->assertRequestStatus(403, 'GET', '/workspace-secrets/'.$secret->getId(), $userId);
        $this->assertRequestStatus(403, 'POST', '/workspace-secrets', $userId, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'NEW_KEY',
            'value' => 'v',
        ]);
        $this->assertRequestStatus(403, 'PATCH', '/workspace-secrets/'.$secret->getId(), $userId, ['value' => 'hijacked']);
        $this->assertRequestStatus(403, 'DELETE', '/workspace-secrets/'.$secret->getId(), $userId);
        $this->assertRequestStatus(403, 'GET', '/workspace-envs/'.$env->getId(), $userId);
        $this->assertRequestStatus(403, 'POST', '/workspace-envs', $userId, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'NEW_VAR',
            'value' => 'v',
        ]);
        $this->assertRequestStatus(403, 'PATCH', '/workspace-envs/'.$env->getId(), $userId, ['value' => 'hijacked']);
        $this->assertRequestStatus(403, 'DELETE', '/workspace-envs/'.$env->getId(), $userId);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertSame('example.test', $em->find(WorkspaceEnv::class, $env->getId())?->getValue());
        $this->assertSame(1, $em->getRepository(WorkspaceEnv::class)->count([]));

        $secret = $this->findSecret($secret->getId());
        $this->assertNotNull($secret);
        $this->assertSame($before, $secret->getValue());
        $this->assertSame(1, self::getEntityManager()->getRepository(WorkspaceSecret::class)->count([]));
    }

    public function testAnonymousUsersAreRejected(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->assertRequestStatus(401, 'GET', '/workspace-secrets?workspace='.$workspace->getId(), null);
        $this->assertRequestStatus(401, 'GET', '/workspace-envs?workspace='.$workspace->getId(), null);
    }

    public function testANameIsUniquePerWorkspace(): void
    {
        $workspace = $this->createSharedWorkspace();
        $other = $this->createWorkspace(['ownerId' => self::OWNER, 'name' => 'Second', 'slug' => 'second']);
        $this->createSecret($workspace, 'BG_KEY', 'a');

        $response = $this->assertRequestStatus(422, 'POST', '/workspace-secrets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BG_KEY',
            'value' => 'b',
        ]);
        $this->assertContains('name', array_column($response->toArray(false)['violations'], 'propertyPath'));

        $this->assertRequestStatus(201, 'POST', '/workspace-secrets', self::OWNER, [
            'workspace' => '/workspaces/'.$other->getId(),
            'name' => 'BG_KEY',
            'value' => 'c',
        ]);
    }

    public static function getInvalidNames(): iterable
    {
        yield 'dash' => ['foo-bar'];
        yield 'leading digit' => ['1ABC'];
        yield 'space' => ['MY KEY'];
        yield 'reference' => ['${KEY}'];
        yield 'too long' => [str_repeat('A', 101)];
        yield 'empty' => [''];
    }

    #[DataProvider('getInvalidNames')]
    public function testAnInvalidNameIsRejected(string $name): void
    {
        $workspace = $this->createSharedWorkspace();

        $response = $this->assertRequestStatus(422, 'POST', '/workspace-secrets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => $name,
            'value' => 'v',
        ]);
        $this->assertContains('name', array_column($response->toArray(false)['violations'], 'propertyPath'));
        $this->assertSame(0, self::getEntityManager()->getRepository(WorkspaceSecret::class)->count([]));
    }

    public function testASecretNeedsAValue(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->assertRequestStatus(400, 'POST', '/workspace-secrets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BG_KEY',
        ]);
    }

    public function testPatchReplacesTheValueButNotTheName(): void
    {
        $workspace = $this->createSharedWorkspace();
        $secret = $this->createSecret($workspace, 'BG_KEY', 'first');
        $before = $secret->getValue();

        $response = $this->assertRequestStatus(200, 'PATCH', '/workspace-secrets/'.$secret->getId(), self::OWNER, ['value' => 'second']);
        $this->assertArrayNotHasKey('value', $response->toArray());
        $secret = $this->findSecret($secret->getId());
        $this->assertNotSame($before, $secret->getValue());
        $this->assertSame('second', $this->decrypt($secret));

        // The same name is accepted, an empty value keeps the current one
        $this->assertRequestStatus(200, 'PATCH', '/workspace-secrets/'.$secret->getId(), self::OWNER, ['name' => 'BG_KEY', 'value' => '']);
        $this->assertSame('second', $this->decrypt($this->findSecret($secret->getId())));

        $this->assertRequestStatus(400, 'PATCH', '/workspace-secrets/'.$secret->getId(), self::OWNER, ['name' => 'RENAMED', 'value' => 'third']);
        $secret = $this->findSecret($secret->getId());
        $this->assertSame('BG_KEY', $secret->getName());
        $this->assertSame('second', $this->decrypt($secret));
    }

    public function testAnEditorDeletesASecret(): void
    {
        $workspace = $this->createSharedWorkspace();
        $secret = $this->createSecret($workspace, 'BG_KEY', 'v');

        $this->assertRequestStatus(204, 'DELETE', '/workspace-secrets/'.$secret->getId(), self::OWNER);
        $this->assertNull($this->findSecret($secret->getId()));
    }

    public function testInstanceSecretsAreManagedByTheAdmins(): void
    {
        $workspace = $this->createSharedWorkspace();
        $this->createSecret($workspace, 'WS_KEY', 'w');

        $this->assertRequestStatus(403, 'GET', '/workspace-secrets?global=true', self::OWNER);
        $this->assertRequestStatus(403, 'POST', '/workspace-secrets', self::OWNER, ['name' => 'GLOBAL_KEY', 'value' => 'v']);
        $this->assertSame(0, self::getEntityManager()->getRepository(WorkspaceSecret::class)->count(['workspace' => null]));

        $data = $this->assertRequestStatus(201, 'POST', '/workspace-secrets', self::ADMIN, ['name' => 'GLOBAL_KEY', 'value' => 'v'])->toArray();
        $this->assertNull($data['workspace'] ?? null);

        $list = $this->assertRequestStatus(200, 'GET', '/workspace-secrets?global=true', self::ADMIN)->toArray();
        $this->assertSame(['GLOBAL_KEY'], array_column($list['hydra:member'] ?? $list['member'], 'name'));

        $this->assertRequestStatus(403, 'GET', '/workspace-secrets/'.$data['id'], self::OWNER);
        $this->assertRequestStatus(403, 'PATCH', '/workspace-secrets/'.$data['id'], self::OWNER, ['value' => 'hijacked']);
        $this->assertRequestStatus(403, 'DELETE', '/workspace-secrets/'.$data['id'], self::OWNER);

        // No SQL unique constraint on a NULL workspace: the validation catches it
        $response = $this->assertRequestStatus(422, 'POST', '/workspace-secrets', self::ADMIN, ['name' => 'GLOBAL_KEY', 'value' => 'other']);
        $this->assertContains('name', array_column($response->toArray(false)['violations'], 'propertyPath'));
        $this->assertSame(1, self::getEntityManager()->getRepository(WorkspaceSecret::class)->count(['workspace' => null]));
    }

    public function testTheCollectionNeedsAScope(): void
    {
        $this->assertRequestStatus(400, 'GET', '/workspace-secrets', self::ADMIN);
        $this->assertRequestStatus(400, 'GET', '/workspace-envs', self::ADMIN);
    }

    public function testVariablesAreListedWithTheirValues(): void
    {
        $workspace = $this->createSharedWorkspace();
        $this->createEnv($workspace, 'HOST', 'example.test');
        $this->createEnv($workspace, 'BASE_URL', 'https://example.test');
        $this->createEnv(null, 'INSTANCE_VAR', 'instance-value');

        $members = $this->assertRequestStatus(200, 'GET', '/workspace-envs?workspace='.$workspace->getId(), self::OWNER)->toArray();
        $members = $members['hydra:member'] ?? $members['member'];
        $this->assertSame(['BASE_URL', 'HOST'], array_column($members, 'name'));
        $this->assertSame(['https://example.test', 'example.test'], array_column($members, 'value'));
        $this->assertSame('/workspaces/'.$workspace->getId(), $members[0]['workspace']);

        $this->assertRequestStatus(403, 'GET', '/workspace-envs?global=true', self::OWNER);
        $list = $this->assertRequestStatus(200, 'GET', '/workspace-envs?global=true', self::ADMIN)->toArray();
        $this->assertSame(['INSTANCE_VAR'], array_column($list['hydra:member'] ?? $list['member'], 'name'));
    }

    public function testAnEditorManagesTheVariablesOfAWorkspace(): void
    {
        $workspace = $this->createSharedWorkspace();

        $data = $this->assertRequestStatus(201, 'POST', '/workspace-envs', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BASE_URL',
            'value' => 'https://example.test',
        ])->toArray();
        $this->assertSame('BASE_URL', $data['name']);
        $this->assertSame('https://example.test', $data['value']);
        $this->assertSame('/workspaces/'.$workspace->getId(), $data['workspace']);

        $this->assertSame('https://example.test', $this->assertRequestStatus(200, 'GET', '/workspace-envs/'.$data['id'], self::OWNER)->toArray()['value']);

        // An empty value is a value; the name cannot change
        $this->assertSame('', $this->assertRequestStatus(200, 'PATCH', '/workspace-envs/'.$data['id'], self::OWNER, ['value' => ''])->toArray()['value'] ?? '');
        $this->assertRequestStatus(200, 'PATCH', '/workspace-envs/'.$data['id'], self::OWNER, ['name' => 'BASE_URL', 'value' => 'https://other.test']);
        $this->assertRequestStatus(400, 'PATCH', '/workspace-envs/'.$data['id'], self::OWNER, ['name' => 'RENAMED', 'value' => 'x']);
        $em = self::getEntityManager();
        $em->clear();
        $env = $em->find(WorkspaceEnv::class, $data['id']);
        $this->assertSame('BASE_URL', $env?->getName());
        $this->assertSame('https://other.test', $env->getValue());

        $response = $this->assertRequestStatus(422, 'POST', '/workspace-envs', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BASE_URL',
            'value' => 'dup',
        ]);
        $this->assertContains('name', array_column($response->toArray(false)['violations'], 'propertyPath'));
        $response = $this->assertRequestStatus(422, 'POST', '/workspace-envs', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'base-url',
            'value' => 'v',
        ]);
        $this->assertContains('name', array_column($response->toArray(false)['violations'], 'propertyPath'));
        $this->assertRequestStatus(400, 'POST', '/workspace-envs', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'NO_VALUE',
        ]);

        $this->assertRequestStatus(204, 'DELETE', '/workspace-envs/'.$data['id'], self::OWNER);
        $this->assertSame(0, self::getEntityManager()->getRepository(WorkspaceEnv::class)->count([]));
    }

    public function testInstanceVariablesAreManagedByTheAdmins(): void
    {
        $this->assertRequestStatus(403, 'POST', '/workspace-envs', self::OWNER, ['name' => 'GLOBAL_VAR', 'value' => 'v']);

        $data = $this->assertRequestStatus(201, 'POST', '/workspace-envs', self::ADMIN, ['name' => 'GLOBAL_VAR', 'value' => 'v'])->toArray();
        $this->assertNull($data['workspace'] ?? null);

        $this->assertRequestStatus(403, 'GET', '/workspace-envs/'.$data['id'], self::OWNER);
        $this->assertRequestStatus(403, 'PATCH', '/workspace-envs/'.$data['id'], self::OWNER, ['value' => 'hijacked']);
        $this->assertRequestStatus(403, 'DELETE', '/workspace-envs/'.$data['id'], self::OWNER);

        // No SQL unique constraint on a NULL workspace: the validation catches it
        $response = $this->assertRequestStatus(422, 'POST', '/workspace-envs', self::ADMIN, ['name' => 'GLOBAL_VAR', 'value' => 'other']);
        $this->assertContains('name', array_column($response->toArray(false)['violations'], 'propertyPath'));
        $this->assertSame(1, self::getEntityManager()->getRepository(WorkspaceEnv::class)->count(['workspace' => null]));
    }

    public function testAnIntegrationResolvesTheReferenceToACreatedVariable(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->assertRequestStatus(201, 'POST', '/workspace-envs', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BG_KEY',
            'value' => 'from-variable',
        ]);
        $integration = $this->createIntegration($workspace, 'remove.bg', ['apiKey' => '${BG_KEY}']);

        $config = self::getService(IntegrationManager::class)->getIntegrationConfiguration($integration);
        $this->assertSame('from-variable', $config['apiKey']);
    }

    /**
     * @param array<string, mixed> $list
     *
     * @return array<array<string, mixed>>
     */
    private static function members(array $list): array
    {
        return $list['hydra:member'] ?? $list['member'];
    }

    private function withSystemVariables(callable $test): void
    {
        foreach (self::SYSTEM_VARIABLES as $name => $value) {
            putenv($name.'='.$value);
        }
        try {
            $test();
        } finally {
            foreach (array_keys(self::SYSTEM_VARIABLES) as $name) {
                putenv($name);
            }
        }
    }

    public function testTheSystemVariablesAreListedByNameToTheWorkspaceEditors(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->withSystemVariables(function () use ($workspace): void {
            $response = $this->assertRequestStatus(200, 'GET', '/system-envs?workspace='.$workspace->getId(), self::OWNER);
            $members = self::members($response->toArray());
            $names = array_column($members, 'name');

            $this->assertContains('FT_SYS_FOO', $names);
            $this->assertNotContains('FT_SYS_EMPTY', $names);
            $this->assertNotContains('PWI_FT_SYS_FOO', $names);
            foreach ($members as $member) {
                $this->assertArrayNotHasKey('value', $member);
            }
            $this->assertStringNotContainsString('system-value', $response->getContent());
        });
    }

    #[DataProvider('getForbiddenUsers')]
    public function testTheSystemVariablesAreForbiddenToNonEditors(bool $reader): void
    {
        if ($reader) {
            $workspace = $this->createSharedWorkspace();
            $userId = self::MEMBER;
        } else {
            $workspace = $this->createOtherWorkspace();
            $userId = self::OWNER;
        }

        $this->assertRequestStatus(403, 'GET', '/system-envs?workspace='.$workspace->getId(), $userId);
    }

    public function testTheSystemVariablesOfTheInstanceIntegrationsAreListedToTheAdmins(): void
    {
        $this->withSystemVariables(function (): void {
            $this->assertRequestStatus(400, 'GET', '/system-envs', self::ADMIN);
            $this->assertRequestStatus(403, 'GET', '/system-envs?global=true', self::OWNER);

            $list = $this->assertRequestStatus(200, 'GET', '/system-envs?global=true', self::ADMIN)->toArray();
            $this->assertContains('FT_SYS_FOO', array_column(self::members($list), 'name'));
        });
    }

    public function testAnonymousUsersCannotListTheSystemVariables(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->assertRequestStatus(401, 'GET', '/system-envs?workspace='.$workspace->getId(), null);
    }

    public function testAnIntegrationResolvesTheReferenceToACreatedSecret(): void
    {
        $workspace = $this->createSharedWorkspace();

        $this->assertRequestStatus(201, 'POST', '/workspace-secrets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'BG_KEY',
            'value' => 'my-plain-key',
        ]);
        $integration = $this->createIntegration($workspace, 'remove.bg', ['apiKey' => '${BG_KEY}']);

        $config = self::getService(IntegrationManager::class)->getIntegrationConfiguration($integration);
        $this->assertSame('my-plain-key', $config['apiKey']);
    }
}
