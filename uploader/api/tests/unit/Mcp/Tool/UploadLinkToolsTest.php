<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\UploadLinkTools;

class UploadLinkToolsTest extends AbstractToolTestCase
{
    private UploadLinkTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new UploadLinkTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 'l1']], 'totalItems' => 1]);

        $this->assertSame(
            ['items' => [['id' => 'l1']], 'totalItems' => 1, 'page' => 1, 'hasNextPage' => false],
            $this->tools->list(),
        );

        $this->assertLastRequest('GET', '/upload-links', ['page' => '1']);
    }

    public function testListByTarget(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('t1', 2);

        $this->assertLastRequest('GET', '/upload-links', ['target' => 't1', 'page' => '2']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 'l1', 'token' => 'abc']);

        $this->assertSame(['id' => 'l1', 'token' => 'abc'], $this->tools->get('l1'));

        $this->assertLastRequest('GET', '/upload-links/l1');
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'l1'], 201);

        $this->assertSame(['id' => 'l1'], $this->tools->create('t1', '2026-12-01T00:00:00+00:00', 'Photographers', 10));

        $this->assertLastRequest('POST', '/upload-links', body: [
            'target' => '/targets/t1',
            'name' => 'Photographers',
            'expiresAt' => '2026-12-01T00:00:00+00:00',
            'maxAssets' => 10,
        ]);
    }

    public function testUpdate(): void
    {
        $this->kernel->willRespond(['id' => 'l1']);

        $this->tools->update('l1', enabled: false, maxTotalSize: 1000);

        $this->assertLastRequest('PATCH', '/upload-links/l1', body: [
            'enabled' => false,
            'maxTotalSize' => 1000,
        ]);
    }

    public function testUpdateClearsTheGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 'l1']);

        $this->tools->update('l1', name: 'Renamed', clear: ['maxAssets', 'expiresAt']);

        $this->assertLastRequest('PATCH', '/upload-links/l1', body: [
            'name' => 'Renamed',
            'maxAssets' => null,
        ]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'l1'], $this->tools->delete('l1'));

        $this->assertLastRequest('DELETE', '/upload-links/l1');
    }

    public function testRegenerateToken(): void
    {
        $this->kernel->willRespond(['id' => 'l1', 'token' => 'new']);

        $this->assertSame(['id' => 'l1', 'token' => 'new'], $this->tools->regenerateToken('l1'));

        $this->assertLastRequest('POST', '/upload-links/l1/regenerate-token', body: []);
    }
}
