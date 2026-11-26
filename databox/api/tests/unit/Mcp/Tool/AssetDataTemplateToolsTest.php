<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Mcp\Tool\AssetDataTemplateTools;

class AssetDataTemplateToolsTest extends AbstractToolTestCase
{
    private AssetDataTemplateTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new AssetDataTemplateTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(collectionId: 'c1', query: 'press');

        $this->assertLastRequest('GET', '/asset-data-templates', ['collection' => 'c1', 'query' => 'press', 'page' => '1', 'limit' => '30']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 't1', 'attributes' => []]);

        $this->assertSame(['id' => 't1', 'attributes' => []], $this->tools->get('t1'));
        $this->assertLastRequest('GET', '/asset-data-templates/t1');
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 't1'], 201);

        $this->tools->create(
            'w1',
            'Press',
            attributes: [['definitionId' => 'd1', 'value' => 'ACME', 'origin' => 'human'], ['definitionId' => 'd2', 'value' => 'Photo', 'locale' => 'en']],
            privacy: 'public_in_workspace',
            collectionId: 'c1',
            includeCollectionChildren: true,
            public: true,
        );

        $this->assertLastRequest('POST', '/asset-data-templates', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Press',
            'attributes' => [['definitionId' => 'd1', 'value' => 'ACME'], ['definitionId' => 'd2', 'value' => 'Photo', 'locale' => 'en']],
            'privacy' => WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE,
            'collection' => '/collections/c1',
            'includeCollectionChildren' => true,
            'public' => true,
        ]);
    }

    public function testCreateWithDefaults(): void
    {
        $this->kernel->willRespond(['id' => 't1'], 201);

        $this->tools->create('w1', 'Press', privacy: 'secret');

        $this->assertLastRequest('POST', '/asset-data-templates', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Press',
            'privacy' => WorkspaceItemPrivacyInterface::SECRET,
            'includeCollectionChildren' => false,
        ]);
    }

    public function testUpdate(): void
    {
        $this->kernel->willRespond(['id' => 't1']);

        $this->tools->update('t1', name: 'Renamed', includeCollectionChildren: true);

        $this->assertLastRequest('PATCH', '/asset-data-templates/t1', body: ['name' => 'Renamed', 'includeCollectionChildren' => true]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 't1'], $this->tools->delete('t1'));
        $this->assertLastRequest('DELETE', '/asset-data-templates/t1');
    }
}
