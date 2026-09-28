<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
use App\Entity\Publication;

class McpTest extends AbstractExposeTestCase
{
    private ?string $sessionId = null;

    public function testUnauthenticatedRequestPointsToResourceMetadata(): void
    {
        $response = $this->request(null, 'POST', '/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'resource_metadata="http://localhost/.well-known/oauth-protected-resource"',
            (string) $response->headers->get('WWW-Authenticate'),
        );

        $response = $this->request(null, 'GET', '/.well-known/oauth-protected-resource');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals('http://localhost/mcp', $json['resource']);
        $this->assertCount(1, $json['authorization_servers']);
    }

    public function testListTools(): void
    {
        $this->initialize(KeycloakClientTestMock::ADMIN_UID);

        $result = $this->rpc(KeycloakClientTestMock::ADMIN_UID, 'tools/list');
        $names = array_column($result['tools'], 'name');

        foreach ([
            'list_publications',
            'get_publication',
            'create_publication',
            'update_publication',
            'delete_publication',
            'sort_publication_assets',
            'list_profiles',
            'create_profile',
            'list_publication_assets',
            'update_asset',
            'delete_asset',
        ] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function testCreateUpdateAndDeletePublication(): void
    {
        $profileId = $this->createProfile(['name' => 'p1', 'layout' => 'download']);
        $this->initialize(KeycloakClientTestMock::ADMIN_UID);

        $publication = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'create_publication', [
            'title' => 'Created by MCP',
            'slug' => 'created-by-mcp',
            'profileId' => $profileId,
            'config' => ['enabled' => true],
        ]);
        $this->assertEquals('Created by MCP', $publication['title']);
        $this->assertEquals(KeycloakClientTestMock::ADMIN_UID, $publication['ownerId']);
        $this->assertEquals($profileId, $publication['profile']['id']);
        $this->assertEquals('download', $publication['layout']);

        $this->assertEquals(
            ['slug' => 'created-by-mcp', 'available' => false],
            $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'check_publication_slug', ['slug' => 'created-by-mcp']),
        );

        $updated = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'update_publication', [
            'id' => $publication['id'],
            'description' => 'Updated',
            'config' => ['layout' => 'grid'],
        ]);
        $this->assertEquals('Created by MCP', $updated['title']);
        $this->assertEquals('Updated', $updated['description']);
        $this->assertEquals('grid', $updated['layout']);

        $bySlug = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'get_publication', ['id' => 'created-by-mcp']);
        $this->assertEquals($publication['id'], $bySlug['id']);

        $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'delete_publication', ['id' => $publication['id']]);
        $this->assertPublicationDoesNotExist($publication['id']);
    }

    public function testListPublications(): void
    {
        $parent = $this->createPublication(['title' => 'Parent']);
        $this->createPublication(['title' => 'Child', 'parent' => $parent]);
        $this->createPublication(['title' => 'Other']);
        $this->initialize(KeycloakClientTestMock::ADMIN_UID);

        $result = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'list_publications');
        $this->assertEquals(2, $result['totalItems']);
        $this->assertEquals(['Other', 'Parent'], array_column($result['items'], 'title'));
        $this->assertFalse($result['hasNextPage']);

        $result = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'list_publications', ['parentId' => $parent->getId()]);
        $this->assertEquals(['Child'], array_column($result['items'], 'title'));
    }

    public function testSortAndUpdateAssets(): void
    {
        $publication = $this->createPublication();
        $a1 = $this->createAsset($publication);
        $a2 = $this->createAsset($publication);
        $this->initialize(KeycloakClientTestMock::ADMIN_UID);

        $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'sort_publication_assets', [
            'id' => $publication->getId(),
            'assetIds' => [$a2, $a1],
        ]);
        $result = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'list_publication_assets', [
            'publicationId' => $publication->getId(),
        ]);
        $this->assertEquals([$a2, $a1], array_column($result['items'], 'id'));

        $asset = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'update_asset', [
            'id' => $a1,
            'title' => 'New title',
            'lat' => 48.85,
        ]);
        $this->assertEquals('New title', $asset['title']);
        $this->assertEquals(48.85, $asset['lat']);

        $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'delete_asset', ['id' => $a1]);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Asset::class, $a1));
    }

    public function testUserPermissionsApply(): void
    {
        $publication = $this->createPublication(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->initialize(KeycloakClientTestMock::USER_UID);

        $error = $this->callTool(KeycloakClientTestMock::USER_UID, 'update_publication', [
            'id' => $publication->getId(),
            'title' => 'Hacked',
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        $error = $this->callTool(KeycloakClientTestMock::USER_UID, 'delete_publication', [
            'id' => $publication->getId(),
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        self::getEntityManager()->clear();
        $this->assertEquals('Foo', self::getEntityManager()->find(Publication::class, $publication->getId())->getTitle());
    }

    public function testValidationErrorIsReported(): void
    {
        $this->createPublication(['slug' => 'taken']);
        $this->initialize(KeycloakClientTestMock::ADMIN_UID);

        $error = $this->callTool(KeycloakClientTestMock::ADMIN_UID, 'create_publication', [
            'title' => 'Duplicate',
            'slug' => 'taken',
        ], expectError: true);
        $this->assertStringContainsString('422', $error);
        $this->assertStringContainsString('slug', $error);
    }

    private function initialize(string $userId): void
    {
        $this->rpc($userId, 'initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ]);
        $this->rpc($userId, 'notifications/initialized', notification: true);
    }

    private function callTool(string $userId, string $name, array $arguments = [], bool $expectError = false): mixed
    {
        $result = $this->rpc($userId, 'tools/call', [
            'name' => $name,
            'arguments' => (object) $arguments,
        ]);

        $this->assertSame($expectError, $result['isError'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));
        if ($expectError) {
            return $result['content'][0]['text'];
        }

        return $result['structuredContent'];
    }

    private function rpc(string $userId, string $method, ?array $params = null, bool $notification = false): ?array
    {
        $body = ['jsonrpc' => '2.0', 'method' => $method];
        if (!$notification) {
            $body['id'] = uniqid();
        }
        if (null !== $params) {
            $body['params'] = $params;
        }

        $server = [
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18',
        ];
        if (null !== $this->sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $this->sessionId;
        }

        $this->clearEmBeforeApiCall();
        $response = $this->request(
            KeycloakClientTestMock::getJwtFor($userId),
            'POST',
            '/mcp',
            server: $server,
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );
        $this->assertTrue($response->isSuccessful(), $response->getContent());
        $this->sessionId ??= $response->headers->get('Mcp-Session-Id');

        if ($notification) {
            return null;
        }

        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('result', $json, $response->getContent());

        return $json['result'];
    }
}
