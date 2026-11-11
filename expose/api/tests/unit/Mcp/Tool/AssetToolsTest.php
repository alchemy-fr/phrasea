<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use App\Mcp\Tool\AssetTools;

class AssetToolsTest extends AbstractToolTestCase
{
    private AssetTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new AssetTools($this->client);
    }

    public function testListInDisplayOrder(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('p1', 2);

        $this->assertLastRequest('GET', '/publications/p1/assets', [
            'order' => ['position' => 'asc', 'createdAt' => 'asc'],
            'page' => '2',
        ]);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->assertSame(['id' => 'a1'], $this->tools->get('a1'));

        $this->assertLastRequest('GET', '/assets/a1');
    }

    public function testUpdateSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->update('a1', title: 'Foo', lat: 0.0);

        // 0.0 is a coordinate, not "unchanged" (JSON encodes it as 0).
        $this->assertLastRequest('PATCH', '/assets/a1', body: ['title' => 'Foo', 'lat' => 0]);
    }

    public function testUpdateAllFields(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->update('a1', 'Foo', 'Desc', ['fr' => ['title' => 'Truc']], 48.85, 2.35);

        $this->assertLastRequest('PATCH', '/assets/a1', body: [
            'title' => 'Foo',
            'description' => 'Desc',
            'translations' => ['fr' => ['title' => 'Truc']],
            'lat' => 48.85,
            'lng' => 2.35,
        ]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'a1'], $this->tools->delete('a1'));

        $this->assertLastRequest('DELETE', '/assets/a1');
    }
}
