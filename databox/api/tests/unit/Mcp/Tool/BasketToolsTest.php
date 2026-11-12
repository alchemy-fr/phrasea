<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\BasketTools;

class BasketToolsTest extends AbstractToolTestCase
{
    private BasketTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new BasketTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(query: 'selection', archived: true);

        $this->assertLastRequest('GET', '/baskets', ['query' => 'selection', 'archived' => 'true', 'page' => '1']);
    }

    public function testCrud(): void
    {
        $this->kernel->willRespond(['id' => 'b1'], 201);
        $this->tools->create('Selection');
        $this->assertLastRequest('POST', '/baskets', body: ['name' => 'Selection']);

        $this->kernel->willRespond(['id' => 'b1']);
        $this->tools->update('b1', description: 'For the catalog');
        $this->assertLastRequest('PATCH', '/baskets/b1', body: ['description' => 'For the catalog']);

        $this->kernel->willRespond(null, 204);
        $this->tools->delete('b1');
        $this->assertLastRequest('DELETE', '/baskets/b1');
    }

    public function testAddToTheDefaultBasket(): void
    {
        $this->kernel->willRespond(['id' => 'b0']);

        $this->tools->add(['a1', 'a2']);

        $this->assertLastRequest('POST', '/baskets/default/assets', body: ['assets' => [['id' => 'a1'], ['id' => 'a2']]]);
    }

    public function testAddAndRemove(): void
    {
        $this->kernel->willRespond(['id' => 'b1']);
        $this->tools->add(['a1'], 'b1');
        $this->assertLastRequest('POST', '/baskets/b1/assets', body: ['assets' => [['id' => 'a1']]]);

        $this->kernel->willRespond(['id' => 'b1']);
        $this->tools->remove('b1', ['i1']);
        $this->assertLastRequest('POST', '/baskets/b1/remove', body: ['items' => ['i1']]);

        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->listAssets('b1', page: 2);
        $this->assertLastRequest('GET', '/baskets/b1/assets', ['page' => '2']);
    }
}
