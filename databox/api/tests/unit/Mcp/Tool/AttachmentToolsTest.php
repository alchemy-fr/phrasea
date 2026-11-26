<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\AttachmentTools;

class AttachmentToolsTest extends AbstractToolTestCase
{
    private AttachmentTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new AttachmentTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 'at1', 'name' => 'Contract']], 'totalItems' => 1]);

        $result = $this->tools->list('a1');

        $this->assertSame([['id' => 'at1', 'name' => 'Contract']], $result['items']);
        $this->assertLastRequest('GET', '/attachments', ['assetId' => 'a1']);
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'at1'], 201);

        $this->tools->create('a1', 'a2', name: 'Contract');

        $this->assertLastRequest('POST', '/attachments', body: ['assetId' => 'a1', 'attachmentId' => 'a2', 'name' => 'Contract']);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'at1'], $this->tools->delete('at1'));
        $this->assertLastRequest('DELETE', '/attachments/at1');
    }
}
