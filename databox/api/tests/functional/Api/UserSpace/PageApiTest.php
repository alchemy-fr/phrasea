<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Page\Page;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Landing pages.
 */
final class PageApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    public function testCreationRequiresCreatePermission(): void
    {
        $payload = ['title' => 'Welcome', 'slug' => 'welcome', 'public' => true, 'enabled' => true];

        $this->assertStatus(403, 'POST', '/pages', self::USER, $payload);
        $this->assertStatus(401, 'POST', '/pages', null, $payload);
        $this->assertSame(0, self::getEntityManager()->getRepository(Page::class)->count([]));
    }

    public function testCreateWithClassLevelCreatePermission(): void
    {
        $this->grantPageCreation(self::USER);

        $data = $this->apiJson('POST', '/pages', self::USER, [
            'title' => 'Welcome',
            'slug' => 'welcome',
            'public' => true,
            'enabled' => true,
            'data' => ['blocks' => [['type' => 'text', 'text' => 'Hello']]],
        ]);
        $this->assertSame('page', $data['@type']);
        $this->assertSame('Welcome', $data['title']);
        $this->assertSame('welcome', $data['slug']);
        $this->assertTrue($data['public']);
        $this->assertTrue($data['enabled']);
        $this->assertSame(['blocks' => [['type' => 'text', 'text' => 'Hello']]], $data['data']);

        $page = self::getEntityManager()->find(Page::class, $data['id']);
        // The owner is the current user
        $this->assertSame(self::USER, $page->getOwnerId());
        $this->assertFalse($page->isHomepage());

        // The creator owns the page and can edit it afterwards
        $this->assertSame('Updated', $this->apiJson('PUT', '/pages/'.$data['id'], self::USER, ['title' => 'Updated'])['title']);
    }

    public function testCreateDefaults(): void
    {
        $data = $this->apiJson('POST', '/pages', self::ADMIN, ['title' => 'Draft']);

        $this->assertFalse($data['public']);
        $this->assertFalse($data['enabled']);
        $this->assertArrayNotHasKey('slug', array_filter($data, static fn ($v): bool => null !== $v));

        $page = self::getEntityManager()->find(Page::class, $data['id']);
        // Without slug, the page is the homepage
        $this->assertTrue($page->isHomepage());
        $this->assertSame(self::ADMIN, $page->getOwnerId());
    }

    /**
     * @dataProvider getInvalidPayloads
     */
    public function testCreateValidation(array $payload): void
    {
        $this->createPage('Existing', 'existing', self::ADMIN);

        $this->assertStatus(422, 'POST', '/pages', self::ADMIN, $payload);
    }

    public static function getInvalidPayloads(): array
    {
        return [
            'missing title' => [['slug' => 'foo']],
            'blank title' => [['title' => '', 'slug' => 'foo']],
            'too long title' => [['title' => str_repeat('a', 101)]],
            'uppercase slug' => [['title' => 'T', 'slug' => 'Foo']],
            'slug with spaces' => [['title' => 'T', 'slug' => 'foo bar']],
            'slug with trailing dash' => [['title' => 'T', 'slug' => 'foo-']],
            'duplicate slug' => [['title' => 'T', 'slug' => 'existing']],
        ];
    }

    public function testEmptySlugIsStoredAsNull(): void
    {
        $data = $this->apiJson('POST', '/pages', self::ADMIN, ['title' => 'Home', 'slug' => '']);

        $this->assertTrue(self::getEntityManager()->find(Page::class, $data['id'])->isHomepage());
    }

    public function testGetBySlugReturnsOnlyEnabledPages(): void
    {
        $this->createPage('Enabled', 'enabled', self::ADMIN, public: true, enabled: true);
        $this->createPage('Disabled', 'disabled', self::ADMIN, public: true, enabled: false);

        $data = $this->apiJson('GET', '/page-by-slug/enabled', null);
        $this->assertSame('Enabled', $data['title']);
        $this->assertSame('enabled', $data['slug']);

        $this->assertStatus(404, 'GET', '/page-by-slug/disabled', self::USER);
        // Even its owner cannot reach a disabled page by slug
        $this->assertStatus(404, 'GET', '/page-by-slug/disabled', self::ADMIN);
        $this->assertStatus(404, 'GET', '/page-by-slug/unknown', self::USER);
    }

    public function testHomePage(): void
    {
        $this->assertStatus(404, 'GET', '/page-by-slug/', null);

        $this->createPage('Home', null, self::ADMIN, public: true, enabled: true);
        $this->assertSame('Home', $this->apiJson('GET', '/page-by-slug/', null)['title']);
    }

    public function testPrivatePageIsNotReadableBySlugByStrangers(): void
    {
        $this->markTestIncomplete('BUG: GET /page-by-slug/{slug} has no security: a private (non-public) enabled page is readable by anonymous users; PageVoter::READ is never evaluated, see src/Entity/Page/Page.php:36-47');

        $page = $this->createPage('Private', 'private', self::ADMIN, public: false, enabled: true);

        $this->assertStatus(401, 'GET', '/page-by-slug/private', null);
        $this->assertStatus(403, 'GET', '/page-by-slug/private', self::USER);
        $this->grantUserOnObject(self::USER, $page, PermissionInterface::VIEW);
        $this->assertStatus(200, 'GET', '/page-by-slug/private', self::USER);
    }

    public function testGetPageItemAccess(): void
    {
        $public = $this->createPage('Public', 'public', self::ADMIN, public: true, enabled: true);

        $data = $this->apiJson('GET', '/pages/'.$public->getId(), null);
        $this->assertSame('Public', $data['title']);
        $this->assertStatus(404, 'GET', '/pages/00000000-0000-4000-8000-000000000000', self::USER);
    }

    public function testPrivateOrDisabledPageIsNotReadableByIdByStrangers(): void
    {
        $this->markTestIncomplete('BUG: GET /pages/{id} has no security: private or disabled pages are readable by anyone, including anonymous users (PageVoter::READ is never evaluated), see src/Entity/Page/Page.php:35');

        $private = $this->createPage('Private', 'private', self::ADMIN, public: false, enabled: true);
        $disabled = $this->createPage('Disabled', 'disabled', self::ADMIN, public: true, enabled: false);

        $this->assertStatus(401, 'GET', '/pages/'.$private->getId(), null);
        $this->assertStatus(403, 'GET', '/pages/'.$private->getId(), self::USER);
        $this->assertStatus(403, 'GET', '/pages/'.$disabled->getId(), self::USER);
        $this->assertStatus(200, 'GET', '/pages/'.$disabled->getId(), self::ADMIN);
    }

    public function testListContainsReadablePagesOnly(): void
    {
        $this->markTestIncomplete('BUG: GET /pages lists every page (private, disabled, of any owner) to anyone, including anonymous users: the collection uses the default Doctrine provider and PageRepository::createQueryBuilderAcl() is never used, see src/Entity/Page/Page.php:31');

        $public = $this->createPage('Public', 'public', self::ADMIN, public: true, enabled: true);
        $this->createPage('Private', 'private', self::ADMIN, public: false, enabled: true);
        $mine = $this->createPage('Mine', 'mine', self::USER, public: false, enabled: false);

        $this->assertSame([$public->getId()], $this->memberIds($this->apiJson('GET', '/pages', null)));

        $ids = $this->memberIds($this->apiJson('GET', '/pages', self::USER));
        sort($ids);
        $expected = [$public->getId(), $mine->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testListUsesListGroup(): void
    {
        $this->createPage('Public', 'public', self::ADMIN, public: true, enabled: true, data: ['blocks' => []]);

        $members = $this->members($this->apiJson('GET', '/pages', self::ADMIN));
        $this->assertCount(1, $members);
        $this->assertSame('Public', $members[0]['title']);
        // "data" belongs to the read group only
        $this->assertArrayNotHasKey('data', $members[0]);
    }

    public function testUpdateAccessMatrix(): void
    {
        $page = $this->createPage('P', 'p', self::USER, public: true, enabled: true);
        $uri = '/pages/'.$page->getId();

        $this->assertStatus(403, 'PUT', $uri, self::OTHER, ['title' => 'Hijack']);
        $this->assertStatus(401, 'PUT', $uri, null, ['title' => 'Hijack']);

        $data = $this->apiJson('PUT', $uri, self::USER, ['enabled' => false]);
        $this->assertFalse($data['enabled']);
        $this->assertSame('P', $data['title']);

        $this->grantUserOnObject(self::OTHER, $page, PermissionInterface::EDIT);
        $this->assertSame('By other', $this->apiJson('PUT', $uri, self::OTHER, ['title' => 'By other'])['title']);
        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);

        $this->assertStatus(200, 'PUT', $uri, self::ADMIN, ['title' => 'By admin']);
    }

    public function testUpdateValidation(): void
    {
        $this->createPage('Other', 'taken', self::USER);
        $page = $this->createPage('P', 'p', self::USER);
        $uri = '/pages/'.$page->getId();

        $this->assertStatus(422, 'PUT', $uri, self::USER, ['slug' => 'taken']);
        $this->assertStatus(422, 'PUT', $uri, self::USER, ['title' => '']);
        // Keeping its own slug is fine
        $this->assertStatus(200, 'PUT', $uri, self::USER, ['slug' => 'p', 'title' => 'Same slug']);
    }

    public function testOwnershipCannotBeChangedByOtherEditor(): void
    {
        $page = $this->createPage('P', 'p', self::USER);
        $this->grantUserOnObject(self::OTHER, $page, PermissionInterface::EDIT);

        $this->markTestIncomplete('BUG: Page has no input DTO nor denormalization groups, so "ownerId" (and "options") are writable through PUT/POST: a user with only EDIT permission can take ownership of the page (then delete it / edit its permissions), see src/Entity/Page/Page.php:28-60');

        $this->api('PUT', '/pages/'.$page->getId(), self::OTHER, ['ownerId' => self::OTHER]);

        self::getEntityManager()->clear();
        $this->assertSame(self::USER, self::getEntityManager()->find(Page::class, $page->getId())->getOwnerId());
    }

    public function testDelete(): void
    {
        $page = $this->createPage('P', 'p', self::USER, public: true, enabled: true);
        $uri = '/pages/'.$page->getId();

        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $this->assertStatus(401, 'DELETE', $uri, null);
        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);
        $this->assertStatus(404, 'GET', '/page-by-slug/p', self::USER);

        $other = $this->createPage('Q', 'q', self::USER);
        $this->grantUserOnObject(self::OTHER, $other, PermissionInterface::DELETE);
        $this->assertStatus(204, 'DELETE', '/pages/'.$other->getId(), self::OTHER);
    }

    private function grantPageCreation(string $userId): void
    {
        self::getPermissionManager()->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER_VALUE,
            $userId,
            Page::OBJECT_TYPE,
            null,
            PermissionInterface::CREATE
        );
    }

    private function createPage(string $title, ?string $slug, string $ownerId, bool $public = false, bool $enabled = false, array $data = []): Page
    {
        $em = self::getEntityManager();
        $page = new Page();
        $page->setTitle($title);
        $page->setSlug($slug);
        $page->setOwnerId($ownerId);
        $page->setPublic($public);
        $page->setEnabled($enabled);
        $page->setData($data);
        $em->persist($page);
        $em->flush();

        return $page;
    }
}
