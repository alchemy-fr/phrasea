<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

final class SavedSearchApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    private const array QUERY = [
        'query' => 'cats',
        'conditions' => [['id' => 'c1', 'query' => 'color = "red"']],
    ];

    public function testCreate(): void
    {
        $data = $this->apiJson('POST', '/saved-searches', self::USER, [
            'name' => 'Red cats',
            'data' => self::QUERY,
            'ownerId' => self::OTHER,
        ]);

        $this->assertSame('saved-search', $data['@type']);
        $this->assertSame('Red cats', $data['name']);
        // Secret by default
        $this->assertSame(SavedSearchPrivacyEnum::Secret->value, $data['privacy']);
        $this->assertSame(self::QUERY, $data['data']);
        $this->assertSame(self::USER, $data['owner']['id']);
        $this->assertSame(['edit' => true, 'delete' => true, 'editPermissions' => true], $data['capabilities']);

        $this->assertSame(self::USER, self::getEntityManager()->find(SavedSearch::class, $data['id'])->getOwnerId());
    }

    public function testCreateRequiresAuthenticatedUser(): void
    {
        $this->markTestIncomplete('BUG: anonymous POST /saved-searches answers 400 "You must provide ownerId" instead of 401: the Post operation has no `security` (only securityPostValidation), see src/Entity/SavedSearch/SavedSearch.php:42');

        $this->assertStatus(401, 'POST', '/saved-searches', null, ['name' => 'Anon', 'data' => []]);
    }

    /**
     * @dataProvider getInvalidPayloads
     */
    public function testCreateValidation(array $payload): void
    {
        $this->assertStatus(422, 'POST', '/saved-searches', self::USER, $payload);
    }

    public static function getInvalidPayloads(): array
    {
        return [
            'missing name' => [['data' => self::QUERY]],
            'blank name' => [['name' => '', 'data' => self::QUERY]],
            'too long name' => [['name' => str_repeat('a', 256), 'data' => self::QUERY]],
        ];
    }

    public function testDataDefaultsToEmptyQuery(): void
    {
        // SavedSearchInput::$data is declared NotNull but input DTOs are not validated: the entity default applies
        $data = $this->apiJson('POST', '/saved-searches', self::USER, ['name' => 'Empty']);
        $this->assertSame([], $data['data']);
    }

    public function testUnknownPrivacyIsRejected(): void
    {
        $this->markTestIncomplete('BUG: an unknown "privacy" answers 500 (ValueError from SavedSearchPrivacyEnum::from()): the Assert\Choice of SavedSearchInput is never evaluated since input DTOs are not validated, see src/Api/InputTransformer/SavedSearchInputTransformer.php:31');

        $this->assertStatus(422, 'POST', '/saved-searches', self::USER, ['name' => 'N', 'data' => self::QUERY, 'privacy' => 42]);
        $search = $this->createSavedSearch('S', self::USER);
        $this->assertStatus(422, 'PUT', '/saved-searches/'.$search->getId(), self::USER, ['privacy' => 9]);
    }

    public function testListVisibility(): void
    {
        $mineSecret = $this->createSavedSearch('A mine secret', self::USER, SavedSearchPrivacyEnum::Secret);
        $otherPublic = $this->createSavedSearch('B other public', self::OTHER, SavedSearchPrivacyEnum::Public);
        $this->createSavedSearch('C other private', self::OTHER, SavedSearchPrivacyEnum::Private);
        $this->createSavedSearch('D other secret', self::OTHER, SavedSearchPrivacyEnum::Secret);
        $granted = $this->createSavedSearch('E other granted', self::OTHER, SavedSearchPrivacyEnum::Secret);
        $this->grantUserOnObject(self::USER, $granted, PermissionInterface::VIEW);

        // Private searches are reachable by link only: not listed to other users
        $this->assertSame(
            [$mineSecret->getId(), $otherPublic->getId(), $granted->getId()],
            $this->memberIds($this->apiJson('GET', '/saved-searches', self::USER))
        );

        $this->assertSame(
            [$otherPublic->getId()],
            $this->memberIds($this->apiJson('GET', '/saved-searches', null))
        );
    }

    public function testListOrderIsByName(): void
    {
        $b = $this->createSavedSearch('Beta', self::USER);
        $a = $this->createSavedSearch('Alpha', self::USER);

        $this->assertSame([$a->getId(), $b->getId()], $this->memberIds($this->apiJson('GET', '/saved-searches', self::USER)));
    }

    /**
     * @dataProvider getReadMatrix
     */
    public function testReadMatrix(SavedSearchPrivacyEnum $privacy, ?string $userId, int $expectedStatus): void
    {
        $search = $this->createSavedSearch('S', self::USER, $privacy);

        $this->assertStatus($expectedStatus, 'GET', '/saved-searches/'.$search->getId(), $userId);
    }

    public static function getReadMatrix(): array
    {
        return [
            'secret / owner' => [SavedSearchPrivacyEnum::Secret, self::USER, 200],
            'secret / other' => [SavedSearchPrivacyEnum::Secret, self::OTHER, 403],
            'secret / anonymous' => [SavedSearchPrivacyEnum::Secret, null, 401],
            'secret / admin' => [SavedSearchPrivacyEnum::Secret, self::ADMIN, 200],
            'private / other' => [SavedSearchPrivacyEnum::Private, self::OTHER, 200],
            'private / anonymous' => [SavedSearchPrivacyEnum::Private, null, 200],
            'public / other' => [SavedSearchPrivacyEnum::Public, self::OTHER, 200],
            'public / anonymous' => [SavedSearchPrivacyEnum::Public, null, 200],
        ];
    }

    public function testSecretSearchSharedThroughAcl(): void
    {
        $search = $this->createSavedSearch('S', self::USER);
        $this->grantUserOnObject(self::OTHER, $search, PermissionInterface::VIEW);

        $data = $this->apiJson('GET', '/saved-searches/'.$search->getId(), self::OTHER);
        $this->assertSame(['edit' => false, 'delete' => false, 'editPermissions' => false], $data['capabilities']);
        $this->assertSame(self::USER, $data['owner']['id']);
    }

    public function testUnknownSearchIs404(): void
    {
        $this->assertStatus(404, 'GET', '/saved-searches/00000000-0000-4000-8000-000000000000', self::USER);
    }

    public function testUpdate(): void
    {
        $search = $this->createSavedSearch('S', self::USER);
        $uri = '/saved-searches/'.$search->getId();

        $data = $this->apiJson('PUT', $uri, self::USER, [
            'name' => 'Renamed',
            'privacy' => SavedSearchPrivacyEnum::Public->value,
            'data' => ['query' => 'dogs'],
        ]);
        $this->assertSame('Renamed', $data['name']);
        $this->assertSame(SavedSearchPrivacyEnum::Public->value, $data['privacy']);
        $this->assertSame(['query' => 'dogs'], $data['data']);

        // Partial update: omitted fields are kept
        $data = $this->apiJson('PUT', $uri, self::USER, ['privacy' => SavedSearchPrivacyEnum::Private->value]);
        $this->assertSame('Renamed', $data['name']);
        $this->assertSame(['query' => 'dogs'], $data['data']);
        $this->assertSame(SavedSearchPrivacyEnum::Private->value, $data['privacy']);

        $this->assertStatus(422, 'PUT', $uri, self::USER, ['name' => '']);
    }

    public function testUpdateAccessMatrix(): void
    {
        $search = $this->createSavedSearch('S', self::USER, SavedSearchPrivacyEnum::Public);
        $uri = '/saved-searches/'.$search->getId();
        $payload = ['name' => 'Hijack', 'data' => []];

        // Public does not mean editable
        $this->assertStatus(403, 'PUT', $uri, self::OTHER, $payload);
        $this->assertStatus(401, 'PUT', $uri, null, $payload);

        $this->grantUserOnObject(self::OTHER, $search, PermissionInterface::EDIT);
        $this->assertSame('By other', $this->apiJson('PUT', $uri, self::OTHER, ['name' => 'By other', 'data' => []])['name']);

        self::getEntityManager()->clear();
        // Editing does not transfer ownership
        $this->assertSame(self::USER, self::getEntityManager()->find(SavedSearch::class, $search->getId())->getOwnerId());
    }

    public function testDelete(): void
    {
        $search = $this->createSavedSearch('S', self::USER, SavedSearchPrivacyEnum::Public);
        $uri = '/saved-searches/'.$search->getId();

        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $this->assertStatus(401, 'DELETE', $uri, null);
        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);

        $other = $this->createSavedSearch('S2', self::USER);
        $this->grantUserOnObject(self::OTHER, $other, PermissionInterface::DELETE);
        $this->assertStatus(204, 'DELETE', '/saved-searches/'.$other->getId(), self::OTHER);

        $third = $this->createSavedSearch('S3', self::USER);
        $this->assertStatus(204, 'DELETE', '/saved-searches/'.$third->getId(), self::ADMIN);
    }

    private function createSavedSearch(string $name, string $ownerId, SavedSearchPrivacyEnum $privacy = SavedSearchPrivacyEnum::Secret): SavedSearch
    {
        $em = self::getEntityManager();
        $search = new SavedSearch();
        $search->setName($name);
        $search->setOwnerId($ownerId);
        $search->setPrivacy($privacy);
        $search->setData(self::QUERY);
        $em->persist($search);
        $em->flush();

        return $search;
    }
}
