<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\CommitTools;

class CommitToolsTest extends AbstractToolTestCase
{
    private CommitTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new CommitTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 'c1']], 'totalItems' => 1]);

        $this->assertSame(
            ['items' => [['id' => 'c1']], 'totalItems' => 1, 'page' => 1, 'hasNextPage' => false],
            $this->tools->list(),
        );

        $this->assertLastRequest('GET', '/commits', ['page' => '1']);
    }

    public function testListFilters(): void
    {
        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->list('t1', false, 3);
        $this->assertLastRequest('GET', '/commits', ['target' => 't1', 'acknowledged' => 'false', 'page' => '3']);

        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->list(acknowledged: true);
        $this->assertLastRequest('GET', '/commits', ['acknowledged' => 'true', 'page' => '1']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 'c1', 'acknowledged' => false]);

        $this->assertSame(['id' => 'c1', 'acknowledged' => false], $this->tools->get('c1'));

        $this->assertLastRequest('GET', '/commits/c1');
    }

    public function testGetAsset(): void
    {
        $this->kernel->willRespond(['id' => 'a1', 'originalName' => 'foo.jpg']);

        $this->assertSame(['id' => 'a1', 'originalName' => 'foo.jpg'], $this->tools->getAsset('a1'));

        $this->assertLastRequest('GET', '/assets/a1');
    }
}
