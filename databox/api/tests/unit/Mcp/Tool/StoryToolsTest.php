<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\StoryTools;

class StoryToolsTest extends AbstractToolTestCase
{
    private StoryTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new StoryTools($this->client);
    }

    public function testListAssets(): void
    {
        $this->kernel->willRespond(['member' => [['position' => 0, 'asset' => ['id' => 'a1']]], 'totalItems' => 1]);

        $result = $this->tools->listAssets('s1', page: 2, limit: 10);

        $this->assertSame([['position' => 0, 'asset' => ['id' => 'a1']]], $result['items']);
        $this->assertLastRequest('GET', '/assets/s1/story-assets', ['page' => '2', 'limit' => '10']);
    }

    public function testSetCover(): void
    {
        $this->kernel->willRespond(['id' => 's1']);

        $this->assertSame(['id' => 's1'], $this->tools->setCover('s1', 'a1'));
        $this->assertLastRequest('PUT', '/assets/s1/story-cover', body: ['assetId' => 'a1']);
    }

    public function testSetPositionInAStory(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['moved' => 'a1'], $this->tools->setPosition('a1', 0, storyId: 's1'));
        $this->assertLastRequest('PUT', '/assets/a1/position', body: ['destination' => '/assets/s1', 'position' => 0]);
    }

    public function testSetPositionInACollection(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->tools->setPosition('a1', 3, collectionId: 'c1');

        $this->assertLastRequest('PUT', '/assets/a1/position', body: ['destination' => '/collections/c1', 'position' => 3]);
    }
}
