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
        $this->kernel->willRespond(['member' => [['id' => 's1']], 'totalItems' => 1]);

        $result = $this->tools->list('p1');

        $this->assertSame([['id' => 's1']], $result['items']);
        $this->assertSame(1, $result['totalItems']);
        $this->assertLastRequest('GET', '/publications/p1/shares', []);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 's1']);

        $this->assertSame(['id' => 's1'], $this->tools->get('s1'));

        $this->assertLastRequest('GET', '/publication-shares/s1');
    }

    public function testCreateWithoutArguments(): void
    {
        $this->kernel->willRespond(['id' => 's1'], 201);

        $this->tools->create('p1');

        $this->assertLastRequest('POST', '/publications/p1/shares', body: []);
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 's1'], 201);

        $this->tools->create('p1', 'For John', '2030-01-01T00:00:00+00:00');

        $this->assertLastRequest('POST', '/publications/p1/shares', body: [
            'name' => 'For John',
            'expiresAt' => '2030-01-01T00:00:00+00:00',
        ]);
    }

    public function testUpdateSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 's1']);

        $this->tools->update('s1', expiresAt: '2030-01-01T00:00:00+00:00');

        $this->assertLastRequest('PATCH', '/publication-shares/s1', body: ['expiresAt' => '2030-01-01T00:00:00+00:00']);
    }

    public function testUpdateRemovesExpiration(): void
    {
        $this->kernel->willRespond(['id' => 's1']);

        $this->tools->update('s1', name: 'Foo', expiresAt: '2030-01-01T00:00:00+00:00', removeExpiration: true);

        $this->assertLastRequest('PATCH', '/publication-shares/s1', body: ['name' => 'Foo', 'expiresAt' => null]);
    }

    public function testRevoke(): void
    {
        $this->kernel->willRespond(['id' => 's1', 'status' => 'revoked']);

        $this->assertSame(['id' => 's1', 'status' => 'revoked'], $this->tools->revoke('s1'));

        $this->assertLastRequest('POST', '/publication-shares/s1/revoke', body: []);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 's1'], $this->tools->delete('s1'));

        $this->assertLastRequest('DELETE', '/publication-shares/s1');
    }
}
