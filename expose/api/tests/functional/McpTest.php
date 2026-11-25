<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\McpBundle\Test\McpClientTrait;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Entity\PublicationShare;
use App\Entity\SubDefinition;
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
            'list_publication_shares',
            'get_publication_share',
            'create_publication_share',
            'update_publication_share',
            'revoke_publication_share',
            'delete_publication_share',
            'list_publication_permissions',
            'set_publication_permission',
            'remove_publication_permission',
            'search_users',
            'search_groups',
            'list_asset_sub_definitions',
            'get_sub_definition',
            'delete_sub_definition',
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

    public function testDetachProfileAndMoveToRoot(): void
    {
        $profileId = $this->createProfile();
        $parent = $this->createPublication(['title' => 'Parent']);
        $publication = $this->createPublication(['title' => 'Child', 'parent' => $parent, 'profile_id' => $profileId]);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $updated = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'update_publication', [
            'id' => $publication->getId(),
            'removeProfile' => true,
            'moveToRoot' => true,
        ]);
        $this->assertNull($updated['profile'] ?? null);
        $this->assertNull($updated['parent'] ?? null);

        self::getEntityManager()->clear();
        $publication = self::getEntityManager()->find(Publication::class, $publication->getId());
        $this->assertNull($publication->getProfile());
        $this->assertNull($publication->getParent());
    }

    public function testListPublicationsByDescription(): void
    {
        $this->createPublication(['title' => 'First']);
        $other = $this->createPublication(['title' => 'Second']);
        $other->setDescription('Holiday pictures');
        self::getEntityManager()->flush();
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publications', [
            'description' => 'holiday',
            'orderBy' => 'createdAt',
        ]);
        $this->assertEquals(['Second'], array_column($result['items'], 'title'));
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

        $asset = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'update_asset', [
            'id' => $a2,
            'slug' => 'second',
            'altitude' => 35.5,
            'trackingId' => 'track-2',
            'webVTT' => [['label' => 'English', 'locale' => 'en', 'content' => "WEBVTT\n\n00:00.000 --> 00:01.000\nHello"]],
        ]);
        $this->assertEquals('second', $asset['slug']);
        $this->assertEquals(35.5, $asset['altitude']);
        $this->assertEquals('track-2', $asset['trackingId']);
        $this->assertCount(1, $asset['webVTTLinks']);
        $this->assertEquals('en', $asset['webVTTLinks'][0]['locale']);

        $updated = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'update_publication', [
            'id' => $publication->getId(),
            'coverAssetId' => $a2,
        ]);
        $this->assertEquals($a2, $updated['cover']['id']);

        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'delete_asset', ['id' => $a1]);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Asset::class, $a1));
    }

    public function testManageShares(): void
    {
        $publication = $this->createPublication(['enabled' => false]);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $share = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'create_publication_share', [
            'publicationId' => $publication->getId(),
            'name' => 'For John',
            'expiresAt' => (new \DateTimeImmutable('+30 days'))->format(\DATE_ATOM),
        ]);
        $this->assertEquals('For John', $share['name']);
        $this->assertEquals(PublicationShare::STATUS_ACTIVE, $share['status']);
        $this->assertStringEndsWith('/s/'.$share['token'], $share['url']);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publication_shares', [
            'publicationId' => $publication->getId(),
        ]);
        $this->assertEquals([$share['id']], array_column($result['items'], 'id'));

        $updated = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'update_publication_share', [
            'id' => $share['id'],
            'removeExpiration' => true,
        ]);
        $this->assertNull($updated['expiresAt'] ?? null);
        $this->assertEquals('For John', $updated['name']);

        $revoked = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'revoke_publication_share', ['id' => $share['id']]);
        $this->assertEquals(PublicationShare::STATUS_REVOKED, $revoked['status']);
        $this->assertEquals(
            PublicationShare::STATUS_REVOKED,
            $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'get_publication_share', ['id' => $share['id']])['status'],
        );

        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'delete_publication_share', ['id' => $share['id']]);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(PublicationShare::class, $share['id']));
    }

    public function testManagePermissions(): void
    {
        $publication = $this->createPublication(['ownerId' => KeycloakClientTestMock::ADMIN_UID]);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $ace = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'set_publication_permission', [
            'objectType' => 'publication',
            'objectId' => $publication->getId(),
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'permissions' => ['VIEW', 'EDIT'],
        ]);
        $this->assertEquals(5, $ace['mask']);
        $this->assertEquals(['VIEW', 'EDIT'], $ace['permissions']);

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publication_permissions', [
            'objectType' => 'publication',
            'objectId' => $publication->getId(),
        ]);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(KeycloakClientTestMock::USER_UID, $result['items'][0]['userId']);
        $this->assertEquals(['VIEW', 'EDIT'], $result['items'][0]['permissions']);

        $this->mcpSessionId = null;
        $this->mcpInitialize(self::token(KeycloakClientTestMock::USER_UID));
        $updated = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'update_publication', [
            'id' => $publication->getId(),
            'title' => 'Edited',
        ]);
        $this->assertEquals('Edited', $updated['title']);

        // Only the owner manages the permissions
        $error = $this->mcpCallTool(self::token(KeycloakClientTestMock::USER_UID), 'set_publication_permission', [
            'objectType' => 'publication',
            'objectId' => $publication->getId(),
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
            'permissions' => ['DELETE'],
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        $this->mcpSessionId = null;
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));
        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'remove_publication_permission', [
            'objectType' => 'publication',
            'objectId' => $publication->getId(),
            'userType' => 'user',
            'userId' => KeycloakClientTestMock::USER_UID,
        ]);
        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_publication_permissions', [
            'objectType' => 'publication',
            'objectId' => $publication->getId(),
        ]);
        $this->assertSame([], $result['items']);
    }

    public function testManageSubDefinitions(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);
        $subDefinitionId = $this->createSubDefinition($assetId, ['name' => 'preview']);
        $this->mcpInitialize(self::token(KeycloakClientTestMock::ADMIN_UID));

        $result = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'list_asset_sub_definitions', [
            'assetId' => $assetId,
        ]);
        $this->assertEquals([$subDefinitionId], array_column($result['items'], 'id'));

        $subDefinition = $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'get_sub_definition', ['id' => $subDefinitionId]);
        $this->assertEquals('preview', $subDefinition['name']);

        $this->mcpCallTool(self::token(KeycloakClientTestMock::ADMIN_UID), 'delete_sub_definition', ['id' => $subDefinitionId]);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(SubDefinition::class, $subDefinitionId));
        $this->assertAssetExists($assetId);
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
