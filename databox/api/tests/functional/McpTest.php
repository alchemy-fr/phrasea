<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\McpBundle\Test\McpClientTrait;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Workspace;
use App\Tests\Functional\Api\Rendition\RenditionTestTrait;
use Symfony\Component\HttpFoundation\Response;

class McpTest extends AbstractDataboxTestCase
{
    use McpClientTrait;
    use RenditionTestTrait;

    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private const string BUILD_DEFINITION = <<<'YAML'
        image:
            transformations:
                -
                    module: imagine
                    options:
                        filters:
                            thumbnail:
                                size: [100, 100]
        YAML;

    public function testUnauthenticatedRequestPointsToResourceMetadata(): void
    {
        $response = $this->mcpRequest(null, 'POST', '/mcp', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString(
            'resource_metadata="http://localhost/.well-known/oauth-protected-resource"',
            (string) $response->headers->get('WWW-Authenticate'),
        );

        foreach (['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'] as $uri) {
            $response = $this->mcpRequest(null, 'GET', $uri);
            $this->assertSame(200, $response->getStatusCode(), $uri);
            $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('http://localhost/mcp', $json['resource']);
            $this->assertCount(1, $json['authorization_servers']);
            $this->assertSame('Phrasea Databox', $json['resource_name']);
        }
    }

    public function testListTools(): void
    {
        $this->mcpInitialize(self::token(self::ADMIN));

        $result = $this->mcpRpc(self::token(self::ADMIN), 'tools/list');
        $this->assertArrayNotHasKey('nextCursor', $result, 'Every tool is listed in a single page');
        $tools = $result['tools'];
        $names = array_column($tools, 'name');

        foreach ([
            'list_workspaces',
            'update_workspace',
            'get_rendition_build_reference',
            'create_rendition_definition',
            'update_rendition_definition',
            'sort_rendition_definitions',
            'list_rendition_policies',
            'list_asset_renditions',
            'create_attribute_definition',
            'list_attribute_policies',
            'list_tags',
            'list_entity_lists',
            'list_integrations',
            'create_collection',
            'move_collection',
            'search_assets',
            'create_asset_from_url',
            'set_asset_attributes',
            'add_to_basket',
            'create_share',
        ] as $name) {
            $this->assertContains($name, $names);
        }

        foreach ($tools as $tool) {
            $this->assertNotEmpty($tool['description'], $tool['name']);
            $this->assertArrayHasKey('annotations', $tool, $tool['name']);
        }
    }

    public function testRenditionDefinitionBuildConfiguration(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('ws-mcp', self::EDITOR);
        $parent = $defaults->renditionDefinitions['main'];
        $this->mcpInitialize(self::token(self::EDITOR));

        $reference = $this->mcpCallTool(self::token(self::EDITOR), 'get_rendition_build_reference');
        $this->assertNotEmpty($reference['reference']);

        $definition = $this->mcpCallTool(self::token(self::EDITOR), 'create_rendition_definition', [
            'workspaceId' => $workspace->getId(),
            'name' => 'Square',
            'policyId' => $defaults->renditionPolicy->getId(),
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => self::BUILD_DEFINITION,
            'parentId' => $parent->getId(),
        ]);
        $this->assertSame('Square', $definition['name']);
        $this->assertSame(self::BUILD_DEFINITION, $definition['definition']);

        $updated = $this->mcpCallTool(self::token(self::EDITOR), 'update_rendition_definition', [
            'id' => $definition['id'],
            'name' => 'Square thumbnail',
        ]);
        $this->assertSame('Square thumbnail', $updated['name']);
        $this->assertSame(self::BUILD_DEFINITION, $updated['definition']);

        $error = $this->mcpCallTool(self::token(self::EDITOR), 'update_rendition_definition', [
            'id' => $definition['id'],
            'definition' => "image:\n    transformations:\n        -\n            module: unknown_module\n",
        ], expectError: true);
        $this->assertStringContainsString('Databox API responded 422', $error);
        $this->assertStringContainsString('definition: ', $error);

        self::getEntityManager()->clear();
        $stored = self::getEntityManager()->find(RenditionDefinition::class, $definition['id']);
        $this->assertSame(self::BUILD_DEFINITION, $stored->getDefinition());
        $this->assertSame($parent->getId(), $stored->getParent()?->getId());
    }

    public function testCollectionLifecycle(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => self::EDITOR]);
        $this->mcpInitialize(self::token(self::EDITOR));

        $parent = $this->mcpCallTool(self::token(self::EDITOR), 'create_collection', [
            'name' => 'Parent',
            'workspaceId' => $workspace->getId(),
        ]);
        $child = $this->mcpCallTool(self::token(self::EDITOR), 'create_collection', [
            'name' => 'Child',
            'parentId' => $parent['id'],
        ]);

        // A partial update keeps the name
        $this->mcpCallTool(self::token(self::EDITOR), 'update_collection', [
            'id' => $child['id'],
            'translations' => ['name' => ['fr' => 'Enfant']],
        ]);
        self::getEntityManager()->clear();
        $stored = self::getEntityManager()->find(Collection::class, $child['id']);
        $this->assertSame('Child', $stored->getName());
        $this->assertSame(['name' => ['fr' => 'Enfant']], $stored->getTranslations());
        $this->assertSame($parent['id'], $stored->getParent()?->getId());

        $this->mcpCallTool(self::token(self::EDITOR), 'move_collection', ['id' => $child['id']]);
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(Collection::class, $child['id'])->getParent());

        $this->assertSame(
            ['trashed' => [$parent['id']]],
            $this->mcpCallTool(self::token(self::EDITOR), 'delete_collections', ['ids' => [$parent['id']]]),
        );
    }

    public function testSetAssetAttributesRecordsMachineValues(): void
    {
        $workspace = $this->createWorkspace();
        $definition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Caption']);
        $asset = $this->createAsset(['workspace' => $workspace]);
        $this->mcpInitialize(self::token(self::ADMIN));

        $this->mcpCallTool(self::token(self::ADMIN), 'set_asset_attributes', [
            'id' => $asset->getId(),
            'actions' => [['action' => 'set', 'definitionId' => $definition->getId(), 'value' => 'A red car']],
            'originVendor' => 'claude',
        ]);

        self::getEntityManager()->clear();
        $attribute = self::getEntityManager()->getRepository(Attribute::class)->findOneBy([
            'asset' => $asset->getId(),
            'definition' => $definition->getId(),
        ]);
        $this->assertSame('A red car', $attribute->getValue());
        $this->assertSame(Attribute::ORIGIN_MACHINE, $attribute->getOrigin());
        $this->assertSame('claude', $attribute->getOriginVendor());
    }

    public function testUserPermissionsApply(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => self::EDITOR]);
        $this->mcpInitialize(self::token(self::OTHER));

        $error = $this->mcpCallTool(self::token(self::OTHER), 'update_workspace', [
            'id' => $workspace->getId(),
            'name' => 'Hacked',
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        $error = $this->mcpCallTool(self::token(self::OTHER), 'create_collection', [
            'name' => 'Intruder',
            'workspaceId' => $workspace->getId(),
        ], expectError: true);
        $this->assertStringContainsString('403', $error);

        self::getEntityManager()->clear();
        $this->assertSame('My workspace', self::getEntityManager()->find(Workspace::class, $workspace->getId())->getName());
    }

    protected function mcpRequest(?string $accessToken, string $method, string $uri, array $server = [], ?string $content = null): Response
    {
        // Start from a clean identity map so that the API reloads what the test set up
        self::getEntityManager()->clear();

        if (null !== $accessToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$accessToken;
        }
        $server['CONTENT_TYPE'] ??= 'application/json';
        $server['HTTP_ACCEPT'] ??= 'application/json';

        $browser = static::createClient()->getKernelBrowser();
        $browser->request($method, $uri, server: $server, content: $content);

        return $browser->getResponse();
    }

    private static function token(string $userId): string
    {
        return KeycloakClientTestMock::getJwtFor($userId);
    }
}
