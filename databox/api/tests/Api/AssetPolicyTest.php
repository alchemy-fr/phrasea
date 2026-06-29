<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\ApiTest\ApiTestCase as AlchemyApiTestCase;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

class AssetPolicyTest extends AbstractSearchTestCase
{
    public function testGetAssetPolicies(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $client->request('GET', '/asset-policies', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        $client->request('GET', '/asset-policies', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'query' => [
                'workspaceId' => $this->findOneBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ])->getId(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/asset-policy',
            '@id' => '/asset-policies',
            '@type' => 'hydra:Collection',
        ]);
    }

    public function testGetAssetPoliciesWithClientScope(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $client->request('GET', '/asset-policies', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('asset-policy:list'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);

        $client->request('GET', '/asset-policies', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('asset-policy:list'),
            ],
            'query' => [
                'workspaceId' => $this->findOneBy(Workspace::class, [
                    'slug' => 'test-workspace',
                ])->getId(),
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/asset-policy',
            '@id' => '/asset-policies',
            '@type' => 'hydra:Collection',
        ]);
    }

    public function testCreateAssetPolicy(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $workspace = $this->findOneBy(Workspace::class, [
            'slug' => 'test-workspace',
        ]);

        $response = $client->request('POST', '/asset-policies', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'users' => [],
                'name' => 'Foo',
                'workspaceId' => $workspace->getId(),
                'conditions' => [],
                'actions' => [],
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('users: At least one user or one group is required.
actions: This collection should contain 1 element or more.', $response->toArray(false)['hydra:description']);

        $rendition = $this->findOneBy(RenditionDefinition::class, [
            'name' => 'preview',
            'workspace' => $workspace->getId(),
        ]);

        $response = $client->request('POST', '/asset-policies', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'users' => [
                    KeycloakClientTestMock::USER_UID,
                ],
                'name' => 'Foo',
                'workspaceId' => $workspace->getId(),
                'conditions' => [],
                'actions' => [
                    [
                        'action' => 'hide_rendition',
                        'id' => $rendition->getId(),
                    ],
                ],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'asset-policy',
            'name' => 'Foo',
            'users' => [
                [
                    'id' => KeycloakClientTestMock::USER_UID,
                    'username' => KeycloakClientTestMock::USERS[KeycloakClientTestMock::USER_UID]['username'],
                ],
            ],
            'groups' => [],
            'actions' => [
                [
                    'action' => 'hide_rendition',
                    'id' => $rendition->getId(),
                ],
            ],
        ]);
        $this->assertMatchesRegularExpression('~^/asset-policies/'.AlchemyApiTestCase::UUID_REGEX.'$~', $response->toArray()['@id']);
    }
}
