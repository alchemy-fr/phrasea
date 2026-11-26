<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\DiscussionTools;

class DiscussionToolsTest extends AbstractToolTestCase
{
    private DiscussionTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new DiscussionTools($this->client);
    }

    public function testListMessages(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 'm1', 'content' => 'Hi']], 'totalItems' => 1]);

        $result = $this->tools->listMessages('t/1', page: 2);

        $this->assertSame([['id' => 'm1', 'content' => 'Hi']], $result['items']);
        $this->assertLastRequest('GET', '/threads/t%2F1/messages', ['page' => '2']);
    }

    public function testPostOnTheThreadOfAnAsset(): void
    {
        $this->kernel->willRespond(['id' => 'm1', 'thread' => ['id' => 't1']], 201);

        $this->assertSame(['id' => 'm1', 'thread' => ['id' => 't1']], $this->tools->post('Looks good', threadKey: 'asset:a1'));
        $this->assertLastRequest('POST', '/messages', body: ['content' => 'Looks good', 'threadKey' => 'asset:a1']);
    }

    public function testPostOnAThread(): void
    {
        $this->kernel->willRespond(['id' => 'm2'], 201);

        $this->tools->post('Done', threadId: 't1');

        $this->assertLastRequest('POST', '/messages', body: ['content' => 'Done', 'threadId' => 't1']);
    }
}
