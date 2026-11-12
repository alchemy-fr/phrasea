<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\CollectionTools;

class CollectionToolsTest extends AbstractToolTestCase
{
    private CollectionTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new CollectionTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(workspaceIds: [2 => 'w1'], parentId: 'c1', deep: true);

        $this->assertLastRequest('GET', '/collections', [
            'workspaces' => ['w1'],
            'parent' => 'c1',
            'deep' => 'true',
            'page' => '1',
            'limit' => '30',
        ]);
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'c2'], 201);

        $this->tools->create('Summer', parentId: 'c1', privacy: 'public_in_workspace');

        $this->assertLastRequest('POST', '/collections', body: [
            'name' => 'Summer',
            'parent' => '/collections/c1',
            'privacyLabel' => 'public_in_workspace',
        ]);
    }

    public function testUpdate(): void
    {
        $this->kernel->willRespond(['id' => 'c1']);

        $this->tools->update('c1', translations: ['name' => ['fr' => 'Été']]);

        $this->assertLastRequest('PATCH', '/collections/c1', body: ['translations' => ['name' => ['fr' => 'Été']]]);
    }

    public function testMove(): void
    {
        $this->kernel->willRespond(null, 204);
        $this->assertSame(['moved' => 'c1', 'parentId' => 'c2'], $this->tools->move('c1', 'c2'));
        $this->assertSame('PUT', $this->kernel->lastRequest()->getMethod());
        $this->assertSame('/collections/c1/move/c2', $this->kernel->lastRequest()->getPathInfo());

        $this->kernel->willRespond(null, 204);
        $this->tools->move('c1');
        $this->assertSame('/collections/c1/move/root', $this->kernel->lastRequest()->getPathInfo());
    }

    public function testDeleteAndRestore(): void
    {
        $this->kernel->willRespond(null, 204);
        $this->assertSame(['trashed' => ['c1', 'c2']], $this->tools->delete(['c1', 'c2']));
        $this->assertLastRequest('POST', '/collections/delete-multiple', body: ['ids' => ['c1', 'c2']]);

        $this->kernel->willRespond(null, 204);
        $this->assertSame(['restored' => ['c1']], $this->tools->restore(['c1']));
        $this->assertLastRequest('POST', '/collections/restore-multiple', body: ['ids' => ['c1']]);
    }
}
