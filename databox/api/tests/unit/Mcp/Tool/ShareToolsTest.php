<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\ShareTools;

class ShareToolsTest extends AbstractToolTestCase
{
    private ShareTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new ShareTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('a1');

        $this->assertLastRequest('GET', '/shares', ['assetId' => 'a1']);
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 's1'], 201);

        $this->tools->create(['a1', 'a2'], expiresAt: '2027-01-01T00:00:00+00:00', renditionDefinitionIds: ['d1']);

        $this->assertLastRequest('POST', '/shares', body: [
            'assets' => ['/assets/a1', '/assets/a2'],
            'expiresAt' => '2027-01-01T00:00:00+00:00',
            'renditionDefinitions' => ['/rendition-definitions/d1'],
        ]);
    }

    public function testUpdateAndDelete(): void
    {
        $this->kernel->willRespond(['id' => 's1']);
        $this->tools->update('s1', enabled: false);
        $this->assertLastRequest('PATCH', '/shares/s1', body: ['enabled' => false]);

        $this->kernel->willRespond(null, 204);
        $this->assertSame(['deleted' => 's1'], $this->tools->delete('s1'));
        $this->assertLastRequest('DELETE', '/shares/s1');
    }
}
