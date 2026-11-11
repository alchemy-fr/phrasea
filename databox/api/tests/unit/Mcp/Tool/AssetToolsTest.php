<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\AssetTools;
use Mcp\Exception\ToolCallException;

class AssetToolsTest extends AbstractToolTestCase
{
    private AssetTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new AssetTools($this->client);
    }

    public function testSearchWithDefaults(): void
    {
        $this->kernel->willRespond(self::emptyCollection() + ['facets' => ['f' => []], 'debug:es' => ['query' => []]]);

        $result = $this->tools->search();

        $this->assertLastRequest('GET', '/assets', ['page' => '1', 'limit' => '20']);
        $this->assertSame('application/ld+json', $this->kernel->lastRequest()->headers->get('Accept'));
        $this->assertArrayNotHasKey('facets', $result);
        $this->assertArrayNotHasKey('debug:es', $result);
    }

    public function testSearchWithFilters(): void
    {
        $this->kernel->willRespond(self::emptyCollection() + ['facets' => ['f' => ['buckets' => []]], 'debug:es' => []]);

        $result = $this->tools->search(
            query: 'sunset',
            conditions: ['@tag IS "t1"', 'title STARTS WITH "Beach"'],
            workspaceIds: ['w1'],
            parentIds: ['c1'],
            collectionId: 'c2',
            storyId: 's1',
            tagsMust: ['t2'],
            tagsMustNot: ['t3'],
            order: ['@createdAt' => 'desc'],
            withFacets: true,
            page: 2,
            limit: 50,
        );

        $this->assertLastRequest('GET', '/assets', [
            'query' => 'sunset',
            'conditions' => ['@tag IS "t1"', 'title STARTS WITH "Beach"'],
            'workspaces' => ['w1'],
            'parents' => ['c1'],
            'collection' => 'c2',
            'story' => 's1',
            'tags_must' => ['t2'],
            'tags_must_not' => ['t3'],
            'order' => ['@createdAt' => 'desc'],
            'page' => '2',
            'limit' => '50',
        ]);
        $this->assertSame(['f' => ['buckets' => []]], $result['facets']);
        $this->assertArrayNotHasKey('debug:es', $result);
    }

    public function testCreateFromUrlFlagsTheAttributesAsMachineValues(): void
    {
        $this->kernel->willRespond(['id' => 'a1'], 201);

        $this->tools->createFromUrl(
            collectionId: 'c1',
            url: 'https://example.com/sunset.jpg',
            name: 'Sunset',
            tagIds: ['t1'],
            attributes: [['definitionId' => 'd1', 'value' => 'Coucher de soleil', 'locale' => 'fr', 'origin' => 'human']],
            originVendor: 'claude',
        );

        $this->assertLastRequest('POST', '/assets', body: [
            'collection' => '/collections/c1',
            'sourceFile' => [
                'url' => 'https://example.com/sunset.jpg',
                'importFile' => false,
            ],
            'name' => 'Sunset',
            'tags' => ['/tags/t1'],
            'attributes' => [[
                'definitionId' => 'd1',
                'value' => 'Coucher de soleil',
                'locale' => 'fr',
                'origin' => 'machine',
                'originVendor' => 'claude',
            ]],
        ]);
    }

    public function testCreateStory(): void
    {
        $this->kernel->willRespond(['id' => 's1'], 201);

        $this->tools->createFromUrl(workspaceId: 'w1', isStory: true, storyAssetIds: ['a1', 'a2']);

        $this->assertLastRequest('POST', '/assets', body: [
            'workspace' => '/workspaces/w1',
            'isStory' => true,
            'storyAssets' => ['a1', 'a2'],
        ]);
    }

    public function testCreateNeedsAContainer(): void
    {
        $this->expectException(ToolCallException::class);

        $this->tools->createFromUrl(url: 'https://example.com/a.jpg');
    }

    public function testUpdate(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->update('a1', privacy: 'secret', tagIds: []);

        $this->assertLastRequest('PATCH', '/assets/a1', body: [
            'privacyLabel' => 'secret',
            'tags' => [],
        ]);
    }

    public function testSetAttributes(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->setAttributes('a1', [
            ['action' => 'set', 'definitionId' => 'd1', 'value' => 'Sunset', 'confidence' => 0.9],
            ['action' => 'add', 'definitionId' => 'd2', 'value' => 'beach', 'originUserId' => 'someone'],
            ['action' => 'delete', 'definitionId' => 'd3', 'id' => 'x1'],
        ]);

        $this->assertLastRequest('POST', '/assets/a1/attributes', body: ['actions' => [
            ['action' => 'set', 'definitionId' => 'd1', 'value' => 'Sunset', 'confidence' => 0.9, 'origin' => 'machine'],
            ['action' => 'add', 'definitionId' => 'd2', 'value' => 'beach', 'origin' => 'machine'],
            ['action' => 'delete', 'definitionId' => 'd3', 'id' => 'x1', 'origin' => 'machine'],
        ]]);
    }

    public function testMoveAndCopy(): void
    {
        $this->kernel->willRespond(null, 204);
        $this->assertSame(['moved' => ['a1']], $this->tools->move(['a1'], collectionId: 'c1'));
        $this->assertLastRequest('POST', '/assets/move', body: ['ids' => ['a1'], 'destination' => '/collections/c1']);

        $this->kernel->willRespond(null, 204);
        $this->tools->copy(['a1'], workspaceId: 'w2', withAttributes: true);
        $this->assertLastRequest('POST', '/assets/copy', body: [
            'ids' => ['a1'],
            'destination' => '/workspaces/w2',
            'byReference' => false,
            'withAttributes' => true,
            'withTags' => false,
        ]);
    }

    public function testMoveNeedsOneDestination(): void
    {
        $this->expectException(ToolCallException::class);

        $this->tools->move(['a1'], collectionId: 'c1', workspaceId: 'w1');
    }

    public function testCollectionMembership(): void
    {
        $this->kernel->willRespond(null, 204);
        $this->tools->addToCollection(['a1', 'a2'], storyId: 's1');
        $this->assertLastRequest('POST', '/assets/add-to-collection', body: ['ids' => ['a1', 'a2'], 'destination' => '/assets/s1']);

        $this->kernel->willRespond(null, 204);
        $this->assertSame(['removed' => 'a1', 'collectionId' => 'c1'], $this->tools->removeFromCollection('a1', 'c1'));
        $this->assertLastRequest('DELETE', '/assets/a1/collections/c1');
    }

    public function testDeleteAndRestore(): void
    {
        $this->kernel->willRespond(null, 204);
        $this->assertSame(['trashed' => ['a1']], $this->tools->delete(['a1']));
        $this->assertLastRequest('POST', '/assets/delete-multiple', body: ['ids' => ['a1']]);

        $this->kernel->willRespond(null, 204);
        $this->tools->restore(['a1']);
        $this->assertLastRequest('POST', '/assets/restore-multiple', body: ['ids' => ['a1']]);
    }

    public function testFindSimilar(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 'a2']], 'totalItems' => 1, 'scores' => ['a2' => 0.9]]);

        $result = $this->tools->findSimilar('a1', 5);

        $this->assertLastRequest('GET', '/assets/a1/similar', ['limit' => '5']);
        $this->assertSame(['a2' => 0.9], $result['scores']);
    }
}
