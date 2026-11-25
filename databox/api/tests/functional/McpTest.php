<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\McpBundle\Test\McpClientTrait;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\Collection;
use App\Entity\Core\EntityList;
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
            'list_entity_lists',
            'list_integrations',
            'create_collection',
            'move_collection',
            'search_assets',
            'create_asset_from_url',
            'set_asset_attributes',
            'add_to_basket',
            'create_share',
            'list_entities',
            'get_entity',
            'create_entity',
            'update_entity',
            'delete_entity',
            'merge_entities',
            'import_entity_list_values',
            'export_entity_list_values',
            'clear_entity_list',
            'batch_update_attributes',
            'build_custom_rendition',
            'set_rendition_from_url',
            'delete_rendition',
            'list_workflows',
            'get_workflow',
            'rerun_workflow_job',
            'cancel_workflow',
            'reprocess_asset',
            'list_field_types',
            'list_metadata_tags',
            'list_locales',
            'list_built_in_attributes',
            'check_quarantined_assets',
            'resolve_quarantined_assets',
            'bypass_quarantine',
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

    public function testEntityLifecycle(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => self::EDITOR]);
        $list = $this->createEntityList($workspace, 'Places');
        $this->mcpInitialize(self::token(self::ADMIN));

        $paris = $this->mcpCallTool(self::token(self::ADMIN), 'create_entity', [
            'listId' => $list->getId(),
            'value' => 'Paris',
            'translations' => ['fr' => 'Paris'],
            'synonyms' => ['_' => ['Lutetia']],
        ]);
        $this->mcpCallTool(self::token(self::ADMIN), 'import_entity_list_values', [
            'id' => $list->getId(),
            'format' => 'raw',
            'data' => "Lyon\nParis\nParis (city)",
        ]);

        $page = $this->mcpCallTool(self::token(self::ADMIN), 'list_entities', ['listId' => $list->getId()]);
        $this->assertSame(['Lyon', 'Paris', 'Paris (city)'], array_column($page['items'], 'value'));
        $duplicate = $page['items'][2]['id'];

        $export = $this->mcpCallTool(self::token(self::ADMIN), 'export_entity_list_values', ['id' => $list->getId()]);
        $this->assertSame('csv', $export['format']);
        $this->assertStringContainsString($paris['id'].',Paris,', $export['content']);

        $this->mcpCallTool(self::token(self::ADMIN), 'update_entity', ['id' => $paris['id'], 'color' => '#ff8800']);
        $this->mcpCallTool(self::token(self::ADMIN), 'merge_entities', ['id' => $paris['id'], 'mergedIds' => [$duplicate]]);

        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(AttributeEntity::class, $duplicate));
        $stored = self::getEntityManager()->find(AttributeEntity::class, $paris['id']);
        $this->assertSame('#ff8800', $stored->getColor());
        $this->assertContains('Paris (city)', $stored->getSynonyms()['_'] ?? []);
    }

    public function testBatchUpdateAttributesRecordsMachineValues(): void
    {
        $workspace = $this->createWorkspace();
        $definition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Credit']);
        $assets = [$this->createAsset(['workspace' => $workspace]), $this->createAsset(['workspace' => $workspace])];
        $assetIds = array_map(static fn (Asset $asset): string => $asset->getId(), $assets);
        $this->mcpInitialize(self::token(self::ADMIN));

        $this->assertSame(['updated' => $assetIds], $this->mcpCallTool(self::token(self::ADMIN), 'batch_update_attributes', [
            'workspaceId' => $workspace->getId(),
            'assetIds' => $assetIds,
            'actions' => [['action' => 'set', 'definitionId' => $definition->getId(), 'value' => 'ACME']],
            'originVendor' => 'claude',
        ]));

        self::getEntityManager()->clear();
        $attributes = self::getEntityManager()->getRepository(Attribute::class)->findBy(['definition' => $definition->getId()]);
        $this->assertCount(2, $attributes);
        foreach ($attributes as $attribute) {
            $this->assertSame('ACME', $attribute->getValue());
            $this->assertSame(Attribute::ORIGIN_MACHINE, $attribute->getOrigin());
            $this->assertSame('claude', $attribute->getOriginVendor());
        }
    }

    public function testDeleteActionRemovesTheValuesOfAnyOrigin(): void
    {
        $workspace = $this->createWorkspace();
        $definition = $this->createAttributeDefinition(['workspace' => $workspace, 'name' => 'Caption']);
        $asset = $this->createAsset(['workspace' => $workspace, 'attributes' => [
            ['definition' => $definition, 'value' => 'Typed by a user', 'origin' => Attribute::ORIGIN_HUMAN],
        ]]);
        $this->mcpInitialize(self::token(self::ADMIN));

        $this->mcpCallTool(self::token(self::ADMIN), 'set_asset_attributes', [
            'id' => $asset->getId(),
            'actions' => [['action' => 'delete', 'definitionId' => $definition->getId()]],
        ]);

        self::getEntityManager()->clear();
        $this->assertSame([], self::getEntityManager()->getRepository(Attribute::class)->findBy(['definition' => $definition->getId()]));
    }

    public function testAttributeDefinitionSearchAndExportSettings(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('ws-mcp-boost', self::EDITOR);
        $preview = $defaults->renditionDefinitions['preview'];
        $this->mcpInitialize(self::token(self::EDITOR));

        $definition = $this->mcpCallTool(self::token(self::EDITOR), 'create_attribute_definition', [
            'workspaceId' => $workspace->getId(),
            'name' => 'Headline',
            'policyId' => $defaults->attributePolicy->getId(),
            'searchBoost' => 3,
            'writeMetadata' => ['XMP-dc:Title'],
            'writeMetadataRenditionIds' => [$preview->getId()],
        ]);
        $this->assertSame(3, $definition['searchBoost']);

        self::getEntityManager()->clear();
        $stored = self::getEntityManager()->find(AttributeDefinition::class, $definition['id']);
        $this->assertSame(3, $stored->getSearchBoost());
        $this->assertSame([$preview->getId()], array_map(
            static fn (RenditionDefinition $d): string => $d->getId(),
            $stored->getWriteMetadataRenditions()->toArray(),
        ));
    }

    public function testRenditionsAndWorkflowsOfAnAsset(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('ws-mcp-renditions', self::EDITOR);
        $asset = $this->createAsset(['workspace' => $workspace, 'ownerId' => self::EDITOR]);
        $rendition = $this->createAssetRendition($asset, $defaults->renditionDefinitions['thumbnail'], $this->createUrlFile($workspace));
        $renditionId = $rendition->getId();
        $this->mcpInitialize(self::token(self::EDITOR));

        $renditions = $this->mcpCallTool(self::token(self::EDITOR), 'list_asset_renditions', ['assetId' => $asset->getId()]);
        $this->assertContains($renditionId, array_column($renditions['items'], 'id'));

        $this->assertSame(
            ['deleted' => $renditionId],
            $this->mcpCallTool(self::token(self::EDITOR), 'delete_rendition', ['id' => $renditionId]),
        );
        self::getEntityManager()->clear();
        $this->assertNull(self::getEntityManager()->find(AssetRendition::class, $renditionId));

        $workflows = $this->mcpCallTool(self::token(self::EDITOR), 'list_workflows', ['assetIds' => [$asset->getId()]]);
        $this->assertSame([], $workflows['items']);
    }

    public function testReferences(): void
    {
        $this->mcpInitialize(self::token(self::EDITOR));

        $types = $this->mcpCallTool(self::token(self::EDITOR), 'list_field_types');
        $this->assertContains('entity', array_column($types['items'], 'name'));

        $builtIns = $this->mcpCallTool(self::token(self::EDITOR), 'list_built_in_attributes');
        $this->assertContains('@createdAt', array_column($builtIns['items'], 'id'));

        $locales = $this->mcpCallTool(self::token(self::EDITOR), 'list_locales', ['query' => 'pt_BR']);
        $this->assertSame(['pt_BR'], array_column($locales['items'], 'id'));
    }

    public function testQuarantineOfAnAcceptedAsset(): void
    {
        $workspace = $this->createWorkspace();
        $asset = $this->createAsset(['workspace' => $workspace]);
        $this->mcpInitialize(self::token(self::ADMIN));

        $check = $this->mcpCallTool(self::token(self::ADMIN), 'check_quarantined_assets', ['ids' => [$asset->getId()]]);
        $this->assertFalse($check['items'][0]['bypass']);
        $this->assertSame('not_quarantined', $check['items'][0]['reasons']['bypass']);

        $result = $this->mcpCallTool(self::token(self::ADMIN), 'resolve_quarantined_assets', [
            'action' => 'bypass',
            'items' => [['id' => $asset->getId()]],
        ]);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame([['id' => $asset->getId(), 'status' => 'skipped', 'reason' => 'not_quarantined']], $result['results']);
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

    private function createEntityList(Workspace $workspace, string $name): EntityList
    {
        $list = new EntityList();
        $list->setWorkspace($workspace);
        $list->setName($name);
        $list->setWithColors(true);
        $list->setWithSynonyms(true);
        $list->setWithTranslations(true);
        self::getEntityManager()->persist($list);
        self::getEntityManager()->flush();

        return $list;
    }

    private static function token(string $userId): string
    {
        return KeycloakClientTestMock::getJwtFor($userId);
    }
}
