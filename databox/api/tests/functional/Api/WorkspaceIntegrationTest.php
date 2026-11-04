<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Platform\IntegrationTestTrait;

/**
 * Integrations are set up on a workspace, or on the whole instance (no
 * workspace) for the integrations that do not require one, e.g. Expose.
 * Instance-wide integrations are managed by the administrators only.
 */
class WorkspaceIntegrationTest extends AbstractDataboxTestCase
{
    use IntegrationTestTrait;

    private const array EXPOSE = [
        'integration' => 'phrasea.expose',
        'name' => 'Expose',
        'public' => true,
        'config' => [
            'baseUrl' => 'https://api-expose.phrasea.test',
            'clientId' => 'expose-app',
            'clientUrl' => 'https://expose.phrasea.test',
        ],
    ];

    public function testAdminCreatesAnInstanceIntegration(): void
    {
        $response = static::createClient()->request('POST', '/integrations', [
            'headers' => $this->getAuthHeaders(KeycloakClientTestMock::ADMIN_UID),
            'json' => self::EXPOSE,
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertNull($data['workspace'] ?? null);
        $this->assertSame('phrasea.expose', $data['integration']);

        $integration = static::getEntityManager()->find(WorkspaceIntegration::class, $data['id']);
        $this->assertNull($integration->getWorkspace());
    }

    public function testSeveralIntegrationsOfTheSameTypeCanBeCreated(): void
    {
        $client = static::createClient();
        $headers = $this->getAuthHeaders(KeycloakClientTestMock::ADMIN_UID);

        $first = $client->request('POST', '/integrations', [
            'headers' => $headers,
            'json' => self::EXPOSE,
        ])->toArray();
        $second = $client->request('POST', '/integrations', [
            'headers' => $headers,
            'json' => [...self::EXPOSE, 'name' => 'Expose 2'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertNotSame($first['id'], $second->toArray()['id']);
    }

    public function testAnIntegrationRequiringAWorkspaceCannotBeInstanceWide(): void
    {
        static::createClient()->request('POST', '/integrations', [
            'headers' => $this->getAuthHeaders(KeycloakClientTestMock::ADMIN_UID),
            'json' => [
                'integration' => 'core.webhook',
                'public' => false,
                'config' => ['url' => 'https://hook.phrasea.test'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUserCannotCreateAnInstanceIntegration(): void
    {
        static::createClient()->request('POST', '/integrations', [
            'headers' => $this->getAuthHeaders(KeycloakClientTestMock::USER_UID),
            'json' => self::EXPOSE,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGlobalFilterOnlyListsInstanceIntegrations(): void
    {
        $client = static::createClient();
        $headers = $this->getAuthHeaders(KeycloakClientTestMock::ADMIN_UID);

        $global = $client->request('POST', '/integrations', [
            'headers' => $headers,
            'json' => self::EXPOSE,
        ])->toArray();
        $workspace = $this->getOrCreateDefaultWorkspace();
        $scoped = $client->request('POST', '/integrations', [
            'headers' => $headers,
            'json' => [
                ...self::EXPOSE,
                'workspace' => '/workspaces/'.$workspace->getId(),
            ],
        ])->toArray();

        $ids = array_column($client->request('GET', '/integrations', [
            'headers' => $headers,
            'query' => ['global' => 1],
        ])->toArray()['member'], 'id');

        $this->assertContains($global['id'], $ids);
        $this->assertNotContains($scoped['id'], $ids);
    }

    public function testIntegrationTypesDescribeTheCatalog(): void
    {
        $response = static::createClient()->request('GET', '/integration-types/phrasea--expose', [
            'headers' => $this->getAuthHeaders(KeycloakClientTestMock::USER_UID),
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Publishes baskets as public galleries on Phrasea Expose.', $data['description']);
        $this->assertSame(['publication'], $data['categories']);
        $this->assertFalse($data['requiresWorkspace']);
        $this->assertSame(['basket'], $data['features']);
    }

    public function testIntegrationTypeDescriptionIsTranslated(): void
    {
        $response = static::createClient()->request('GET', '/integration-types/phrasea--expose', [
            'headers' => [
                ...$this->getAuthHeaders(KeycloakClientTestMock::USER_UID),
                'Accept-Language' => 'fr',
            ],
        ]);

        $this->assertSame('Publie les paniers sous forme de galeries publiques sur Phrasea Expose.', $response->toArray()['description']);
    }

    private function duplicate(string $id, ?string $userId): array
    {
        self::getEntityManager()->clear();

        return static::createClient()->request('POST', '/integrations/'.$id.'/duplicate', [
            'headers' => $this->headers($userId),
            'json' => [],
        ])->toArray(false);
    }

    private function findIntegration(string $id): WorkspaceIntegration
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(WorkspaceIntegration::class, $id);
    }

    public function testDuplicate(): void
    {
        $workspace = $this->createSharedWorkspace();
        $need = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, name: 'Expose');
        $source = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, public: true, name: 'Publication', ownerId: 'someone-else');
        $source->addNeed($need);
        $source->setIf('asset.getWorkspaceId() is not null');
        $source->appendError(['message' => 'Failure']);
        self::getEntityManager()->flush();
        $this->createIntegrationData($source, self::OWNER);
        $this->createIntegrationToken($source, self::OWNER);

        $data = $this->duplicate($source->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('integration', $data['@type']);
        $this->assertNotSame($source->getId(), $data['id']);
        $this->assertSame('Publication (copy)', $data['name']);
        $this->assertFalse($data['enabled'], 'the copy is disabled not to be triggered twice');
        $this->assertSame(['/integrations/'.$need->getId()], $data['needs']);
        $this->assertSame([], $data['tokens'] ?? [], 'the tokens are not copied');

        $copy = $this->findIntegration($data['id']);
        $this->assertSame($workspace->getId(), $copy->getWorkspaceId());
        $this->assertSame('phrasea.expose', $copy->getIntegration());
        $this->assertSame(self::OWNER, $copy->getOwnerId(), 'the copy is owned by the caller');
        $this->assertTrue($copy->getPublic());
        $this->assertSame('asset.getWorkspaceId() is not null', $copy->getIf());
        $this->assertSame(self::EXPOSE_CONFIG, $copy->getConfig());
        $this->assertSame([], $copy->getLastErrors());

        $em = self::getEntityManager();
        $this->assertCount(0, $em->getRepository(IntegrationData::class)->findBy(['integration' => $copy->getId()]));
        $this->assertCount(0, $em->getRepository(IntegrationToken::class)->findBy(['integration' => $copy->getId()]));
        $this->assertCount(1, $em->getRepository(IntegrationData::class)->findBy(['integration' => $source->getId()]));
        $this->assertCount(1, $em->getRepository(IntegrationToken::class)->findBy(['integration' => $source->getId()]));

        // The original is unchanged
        $original = $this->findIntegration($source->getId());
        $this->assertSame('Publication', $original->getName());
        $this->assertTrue($original->isEnabled());
        $this->assertSame('someone-else', $original->getOwnerId());
        $this->assertCount(1, $original->getLastErrors());
    }

    public function testDuplicateNamesTheCopiesAfterTheOriginal(): void
    {
        $workspace = $this->createSharedWorkspace();
        $source = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, name: 'Publication');

        $first = $this->duplicate($source->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Publication (copy)', $first['name']);

        $second = $this->duplicate($first['id'], self::OWNER);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Publication (copy 2)', $second['name'], 'the copy of a copy is named after the original');

        $third = $this->duplicate($source->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Publication (copy 3)', $third['name']);
    }

    public function testDuplicateAnUnnamedIntegrationIsNamedAfterItsType(): void
    {
        $workspace = $this->createSharedWorkspace();
        $source = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG);

        $data = $this->duplicate($source->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Expose (copy)', $data['name']);
        $this->assertNull($this->findIntegration($source->getId())->getName());
    }

    public function testDuplicateAnUploaderIntegrationRenewsItsSecurityKey(): void
    {
        $workspace = $this->createSharedWorkspace();
        $source = $this->createIntegration($workspace, UploaderIntegration::NAME, [
            'baseUrl' => 'https://uploader.test',
            'securityKey' => 'uploader-secret-key',
        ], name: 'Uploader');

        $data = $this->duplicate($source->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(201);

        $config = $this->findIntegration($data['id'])->getConfig();
        $this->assertSame('https://uploader.test', $config['baseUrl']);
        $this->assertNotEmpty($config['securityKey']);
        $this->assertNotSame('uploader-secret-key', $config['securityKey'], 'a security key is not shared by two integrations');
        $this->assertSame('uploader-secret-key', $this->findIntegration($source->getId())->getConfig()['securityKey']);
    }

    public function testDuplicateRequiresTheWorkspaceEditPermission(): void
    {
        $workspace = $this->createSharedWorkspace();
        $source = $this->createIntegration($workspace, 'phrasea.expose', self::EXPOSE_CONFIG, name: 'Publication');

        $this->duplicate($source->getId(), null);
        $this->assertResponseStatusCodeSame(401);

        $this->duplicate($source->getId(), self::MEMBER);
        $this->assertResponseStatusCodeSame(403);

        // Editing the integration does not allow to create one in the workspace
        $this->grantUserOnObject(self::MEMBER, $this->findIntegration($source->getId()), PermissionInterface::EDIT);
        $this->duplicate($source->getId(), self::MEMBER);
        $this->assertResponseStatusCodeSame(403);

        $this->duplicate('9b2a7f5e-0000-4000-8000-000000000000', self::OWNER);
        $this->assertResponseStatusCodeSame(404);

        $this->assertCount(1, self::getEntityManager()->getRepository(WorkspaceIntegration::class)->findBy(['name' => ['Publication', 'Publication (copy)']]));
    }

    public function testAdminDuplicatesAnInstanceIntegration(): void
    {
        $source = $this->createIntegration(null, 'phrasea.expose', self::EXPOSE_CONFIG, public: true, ownerId: KeycloakClientTestMock::ADMIN_UID);

        $this->duplicate($source->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        $data = $this->duplicate($source->getId(), KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Expose (copy)', $data['name']);
        $this->assertNull($data['workspace'] ?? null);

        $copy = $this->findIntegration($data['id']);
        $this->assertNull($copy->getWorkspace());
        $this->assertFalse($copy->isEnabled());
        $this->assertSame(KeycloakClientTestMock::ADMIN_UID, $copy->getOwnerId());

        $second = $this->duplicate($source->getId(), KeycloakClientTestMock::ADMIN_UID);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('Expose (copy 2)', $second['name']);
    }

    private function getAuthHeaders(string $userId): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }
}
