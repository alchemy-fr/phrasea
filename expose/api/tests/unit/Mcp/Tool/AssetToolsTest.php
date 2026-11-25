<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
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

    public function testUpdateExtraFields(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $webVTT = [
            3 => ['label' => 'Français', 'locale' => 'fr', 'content' => "WEBVTT\n\n00:00.000 --> 00:01.000\nBonjour"],
        ];
        $this->tools->update(
            'a1',
            altitude: 0.0,
            slug: 'my-asset',
            position: 0,
            trackingId: 't1',
            webVTT: $webVTT,
        );

        $this->assertLastRequest('PATCH', '/assets/a1', body: [
            'altitude' => 0,
            'slug' => 'my-asset',
            'position' => 0,
            'trackingId' => 't1',
            'webVTT' => [$webVTT[3]],
        ]);
    }

    public function testUpdateRemovesAllWebVTT(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->update('a1', webVTT: []);

        $this->assertLastRequest('PATCH', '/assets/a1', body: ['webVTT' => []]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'a1'], $this->tools->delete('a1'));

        $this->assertLastRequest('DELETE', '/assets/a1');
    }
}
