<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\McpBundle\Test\McpClientTrait;
use App\Entity\Asset;
use App\Entity\Publication;
use Symfony\Component\HttpFoundation\Response;

class McpTest extends AbstractExposeTestCase
{
    use McpClientTrait;

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
        $this->assertEquals('Phrasea Expose', $json['resource_name']);

        // The variant suffixed with the resource path, tried first by MCP clients
        $response = $this->request(null, 'GET', '/.well-known/oauth-protected-resource/mcp');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost/mcp', json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['resource']);
    }

    public function testListTools(): void
    {
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $result = $this->mcpRpc(self::token(KeycloakClientTestMock::ADMIN_UID), 'tools/list');
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
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $publication = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'create_publication', [
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
            $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'check_publication_slug', ['slug' => 'created-by-mcp']),
        );

        $updated = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'update_publication', [
            'id' => $publication['id'],
            'description' => 'Updated',
            'config' => ['layout' => 'grid'],
        ]);
        $this->assertEquals('Created by MCP', $updated['title']);
        $this->assertEquals('Updated', $updated['description']);
        $this->assertEquals('grid', $updated['layout']);

        $bySlug = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'get_publication', ['id' => 'created-by-mcp']);
        $this->assertEquals($publication['id'], $bySlug['id']);

        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'delete_publication', ['id' => $publication['id']]);
        $this->assertPublicationDoesNotExist($publication['id']);
    }

    public function testListPublications(): void
    {
        $parent = $this->createPublication(['title' => 'Parent']);
        $this->createPublication(['title' => 'Child', 'parent' => $parent]);
        $this->createPublication(['title' => 'Other']);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publications');
        $this->assertEquals(2, $result['totalItems']);
        $this->assertEquals(['Other', 'Parent'], array_column($result['items'], 'title'));
        $this->assertFalse($result['hasNextPage']);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publications', ['parentId' => $parent->getId()]);
        $this->assertEquals(['Child'], array_column($result['items'], 'title'));
    }

    public function testSortAndUpdateAssets(): void
    {
        $publication = $this->createPublication();
        $a1 = $this->createAsset($publication);
        $a2 = $this->createAsset($publication);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'sort_publication_assets', [
            'id' => $publication->getId(),
            'assetIds' => [$a2, $a1],
        ]);
        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publication_assets', [
            'publicationId' => $publication->getId(),
        ]);
        $this->assertEquals([$a2, $a1], array_column($result['items'], 'id'));

        $asset = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'update_asset', [
            'id' => $a1,
            'title' => 'New title',
            'lat' => 48.85,
        ]);
        $this->assertEquals('New title', $asset['title']);
        $this->assertEquals(48.85, $asset['lat']);

        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'delete_asset', ['id' => $a1]);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Asset::class, $a1));
    }

    public function testUserPermissionsApply(): void
    {
        $publication = $this->createPublication(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'update_publication', [
            'id' => $publication->getId(),
            'title' => 'Hacked',
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'delete_publication', [
            'id' => $publication->getId(),
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        self::getEntityManager()->clear();
        $this->assertEquals('Foo', self::getEntityManager()->find(Publication::class, $publication->getId())->getTitle());
    }

    public function testValidationErrorIsReported(): void
    {
        $this->createPublication(['slug' => 'taken']);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'create_publication', [
            'title' => 'Duplicate',
            'slug' => 'taken',
        ], expectError: true);
        $this->assertStringContainsString('422', $error);
        $this->assertStringContainsString('slug', $error);
    }

    protected function mcpRequest(?string $accessToken, string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        $this->clearEmBeforeApiCall();

        return $this->request($accessToken, $method, $uri, server: $server, content: $content);
    }

    private static function token(string $userId): string
    {
        return KeycloakClientTestMock::getJwtFor($userId);
    }
}
