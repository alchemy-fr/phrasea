<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Integration\WorkspaceIntegration;
use App\Tests\AbstractDataboxTestCase;

/**
 * Integrations are set up on a workspace, or on the whole instance (no
 * workspace) for the integrations that do not require one, e.g. Expose.
 * Instance-wide integrations are managed by the administrators only.
 */
class WorkspaceIntegrationTest extends AbstractDataboxTestCase
{
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
        ])->toArray()['hydra:member'], 'id');

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

    private function getAuthHeaders(string $userId): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }
}
