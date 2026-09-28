<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use App\Mcp\Tool\PublicationTools;
use PHPUnit\Framework\Attributes\DataProvider;

class PublicationToolsTest extends AbstractToolTestCase
{
    private PublicationTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new PublicationTools($this->client);
    }

    public function testListWithDefaults(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list();

        $this->assertLastRequest('GET', '/publications', [
            'page' => '1',
            'order' => ['title' => 'asc'],
        ]);
    }

    public function testListWithFilters(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(
            title: 'foo',
            parentId: 'p1',
            profileId: 'pr1',
            flatten: true,
            mine: true,
            editable: true,
            expired: true,
            empty: true,
            disabled: true,
            orderBy: 'createdAt',
            page: 3,
        );

        $this->assertLastRequest('GET', '/publications', [
            'title' => 'foo',
            'parentId' => 'p1',
            'profileId' => 'pr1',
            'flatten' => 'true',
            'mine' => 'true',
            'editable' => 'true',
            'expired' => 'true',
            'empty' => 'true',
            'disabled' => 'true',
            'page' => '3',
            'order' => ['createdAt' => 'desc'],
        ]);
    }

    public function testListDropsAuthorizedFlag(): void
    {
        $this->kernel->willRespond([
            'member' => [['id' => '1', 'title' => 'Foo', 'authorized' => false]],
            'totalItems' => 1,
        ]);

        $result = $this->tools->list();

        $this->assertSame([['id' => '1', 'title' => 'Foo']], $result['items']);
        $this->assertSame(1, $result['totalItems']);
    }

    public function testGetEncodesIdentifier(): void
    {
        $this->kernel->willRespond(['id' => '1']);

        $this->assertSame(['id' => '1'], $this->tools->get('my slug/../x'));

        $this->assertSame('/publications/my%20slug%2F..%2Fx', $this->kernel->lastRequest()->getRequestUri());
    }

    #[DataProvider('getSlugAvailability')]
    public function testCheckSlug(mixed $response, bool $available): void
    {
        $this->kernel->willRespond($response);

        $this->assertSame(['slug' => 'foo', 'available' => $available], $this->tools->checkSlug('foo'));
        $this->assertLastRequest('GET', '/publications/slug-availability/foo');
    }

    public static function getSlugAvailability(): array
    {
        return [
            [true, true],
            [false, false],
        ];
    }

    public function testCreateWithTitleOnly(): void
    {
        $this->kernel->willRespond(['id' => '1'], 201);

        $this->assertSame(['id' => '1'], $this->tools->create('Foo'));

        $this->assertLastRequest('POST', '/publications', body: ['title' => 'Foo']);
    }

    public function testCreateMapsRelationsToIris(): void
    {
        $this->kernel->willRespond(['id' => '1'], 201);

        $this->tools->create(
            title: 'Foo',
            description: 'Desc',
            slug: 'foo',
            profileId: 'pr1',
            parentId: 'p1',
            translations: ['fr' => ['title' => 'Truc']],
            config: ['enabled' => false, 'layout' => 'grid'],
        );

        $this->assertLastRequest('POST', '/publications', body: [
            'title' => 'Foo',
            'description' => 'Desc',
            'slug' => 'foo',
            'profile' => '/publication-profiles/pr1',
            'parent' => '/publications/p1',
            'translations' => ['fr' => ['title' => 'Truc']],
            'config' => ['enabled' => false, 'layout' => 'grid'],
        ]);
    }

    public function testUpdateSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => '1']);

        $this->tools->update('1', description: '', config: ['theme' => 'dark']);

        // An empty string is a value (clears the description), unlike null which means "unchanged".
        $this->assertLastRequest('PATCH', '/publications/1', body: [
            'description' => '',
            'config' => ['theme' => 'dark'],
        ]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => '1'], $this->tools->delete('1'));

        $this->assertLastRequest('DELETE', '/publications/1');
    }

    public function testSortAssetsReindexesIds(): void
    {
        $this->kernel->willRespond(['id' => '1']);

        $this->tools->sortAssets('1', [3 => 'a3', 7 => 'a1']);

        $this->assertLastRequest('POST', '/publications/1/sort-assets', body: ['order' => ['a3', 'a1']]);
    }
}
