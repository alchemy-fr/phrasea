<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\TagTools;

class TagToolsTest extends AbstractToolTestCase
{
    private TagTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new TagTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(workspaceId: 'w1', query: 'red');

        $this->assertLastRequest('GET', '/tags', [
            'workspace' => 'w1',
            'query' => 'red',
            'page' => '1',
            'limit' => '30',
        ]);
    }

    public function testCrud(): void
    {
        $this->kernel->willRespond(['id' => 't1'], 201);
        $this->tools->create('w1', 'Red', color: '#ff0000', translations: ['name' => ['fr' => 'Rouge']]);
        $this->assertLastRequest('POST', '/tags', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Red',
            'color' => '#ff0000',
            'translations' => ['name' => ['fr' => 'Rouge']],
        ]);

        $this->kernel->willRespond(['id' => 't1']);
        $this->tools->get('t1');
        $this->assertLastRequest('GET', '/tags/t1');

        $this->kernel->willRespond(['id' => 't1']);
        $this->tools->update('t1', color: '#00ff00');
        $this->assertLastRequest('PATCH', '/tags/t1', body: ['color' => '#00ff00']);

        $this->kernel->willRespond(null, 204);
        $this->assertSame(['deleted' => 't1'], $this->tools->delete('t1'));
        $this->assertLastRequest('DELETE', '/tags/t1');
    }
}
