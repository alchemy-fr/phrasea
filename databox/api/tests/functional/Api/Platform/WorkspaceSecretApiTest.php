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
 * (by the admins for the instance ones), values never returned.
 */
final class WorkspaceSecretApiTest extends AbstractDataboxTestCase
{
    use IntegrationTestTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

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

    public function testVariablesAreListedByNameOnly(): void
    {
        $workspace = $this->createSharedWorkspace();
        $this->createEnv($workspace, 'HOST', 'sensitive-host-value');
        $this->createEnv($workspace, 'BASE_URL', 'https://example.test');
        $this->createEnv(null, 'INSTANCE_VAR', 'instance-value');

        $response = $this->assertRequestStatus(200, 'GET', '/workspace-envs?workspace='.$workspace->getId(), self::OWNER);
        $this->assertStringNotContainsString('sensitive-host-value', $response->getContent());
        $members = $response->toArray()['hydra:member'] ?? $response->toArray()['member'];
        $this->assertSame(['BASE_URL', 'HOST'], array_column($members, 'name'));
        foreach ($members as $member) {
            $keys = array_values(array_filter(array_keys($member), static fn (string $k): bool => !str_starts_with($k, '@')));
            sort($keys);
            $this->assertSame(['id', 'name'], $keys);
        }

        $this->assertRequestStatus(403, 'GET', '/workspace-envs?global=true', self::OWNER);
        $list = $this->assertRequestStatus(200, 'GET', '/workspace-envs?global=true', self::ADMIN)->toArray();
        $this->assertSame(['INSTANCE_VAR'], array_column($list['hydra:member'] ?? $list['member'], 'name'));
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
