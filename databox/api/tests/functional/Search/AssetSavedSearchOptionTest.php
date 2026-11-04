<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use App\Tests\Functional\AbstractSearchTestCase;
use App\Tests\Functional\Api\UserSpace\UserSpaceTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The `savedSearch` option of the asset searches: the saved search is the base of the
 * search, the request refines it but cannot widen it.
 */
class AssetSavedSearchOptionTest extends AbstractSearchTestCase
{
    use UserSpaceTestTrait;

    private const string CITY = 'city_text_s';

    /**
     * @var array<string, string> collection IDs by name
     */
    private array $collections = [];

    /**
     * @var array<string, string> asset IDs by name
     */
    private array $assets = [];

    /**
     * Three public assets, each in its own collection: "In A" (Paris), "In B" (Amsterdam), "In C" (Berlin).
     */
    private function createAssets(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $city = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'City',
            'sortable' => true,
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();

        foreach ([
            'A' => 'Paris',
            'B' => 'Amsterdam',
            'C' => 'Berlin',
        ] as $collectionName => $cityName) {
            $collection = $this->createCollection([
                'workspace' => $workspace,
                'name' => $collectionName,
                'public' => true,
            ]);
            $this->collections[$collectionName] = $collection->getId();

            $name = 'In '.$collectionName;
            $this->assets[$name] = $this->createAsset([
                'workspace' => $workspace,
                'name' => $name,
                'public' => true,
                'collectionId' => $collection->getId(),
                'attributes' => [
                    [
                        'definition' => $city,
                        'value' => $cityName,
                    ],
                ],
            ])->getId();
        }

        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    private function createSavedSearch(array $data, string $ownerId = self::USER, SavedSearchPrivacyEnum $privacy = SavedSearchPrivacyEnum::Secret): SavedSearch
    {
        $savedSearch = new SavedSearch();
        $savedSearch->setName('S');
        $savedSearch->setOwnerId($ownerId);
        $savedSearch->setPrivacy($privacy);
        $savedSearch->setData($data);

        $em = self::getEntityManager();
        $em->persist($savedSearch);
        $em->flush();

        return $savedSearch;
    }

    /**
     * @return string[] the names of the matching assets, in result order
     */
    private function searchNames(array $query, ?string $userId = self::USER): array
    {
        $data = $this->apiJson('GET', '/assets', $userId, query: $query);

        return array_map(fn (array $asset): ?string => $asset['name'] ?? null, $this->members($data));
    }

    /**
     * @return string[] the names of the matching assets, sorted
     */
    private function searchSortedNames(array $query, ?string $userId = self::USER): array
    {
        $names = $this->searchNames($query, $userId);
        sort($names);

        return $names;
    }

    private function inCollections(string ...$names): string
    {
        return sprintf('@collection IS ANY OF (%s)', implode(', ', array_map(
            fn (string $name): string => sprintf('"%s"', $this->collections[$name]),
            $names
        )));
    }

    #[DataProvider('getReadMatrix')]
    public function testReadMatrix(SavedSearchPrivacyEnum $privacy, ?string $userId, bool $grantView, int $expectedStatus): void
    {
        $this->createAssets();
        $savedSearch = $this->createSavedSearch(['query' => 'Paris'], self::USER, $privacy);
        if ($grantView) {
            $this->grantUserOnObject(self::OTHER, $savedSearch, PermissionInterface::VIEW);
        }

        $query = ['savedSearch' => $savedSearch->getId()];
        $this->assertStatus($expectedStatus, 'GET', '/assets', $userId, query: $query);
        if (200 === $expectedStatus) {
            $this->assertSame(['In A'], $this->searchSortedNames($query, $userId));
        }
    }

    public static function getReadMatrix(): array
    {
        return [
            'secret / owner' => [SavedSearchPrivacyEnum::Secret, self::USER, false, 200],
            'secret / other' => [SavedSearchPrivacyEnum::Secret, self::OTHER, false, 403],
            'secret / anonymous' => [SavedSearchPrivacyEnum::Secret, null, false, 401],
            'secret / admin' => [SavedSearchPrivacyEnum::Secret, self::ADMIN, false, 200],
            'secret / other granted VIEW' => [SavedSearchPrivacyEnum::Secret, self::OTHER, true, 200],
            'private / other' => [SavedSearchPrivacyEnum::Private, self::OTHER, false, 200],
            'public / anonymous' => [SavedSearchPrivacyEnum::Public, null, false, 200],
        ];
    }

    public function testGeoClustersDeniesUnreadableSavedSearch(): void
    {
        $savedSearch = $this->createSavedSearch(['query' => 'Paris'], self::USER, SavedSearchPrivacyEnum::Secret);

        $this->assertStatus(403, 'GET', '/assets/geo-clusters', self::OTHER, query: [
            'field' => 'location_geo-point_s',
            'precision' => 3,
            'savedSearch' => $savedSearch->getId(),
        ]);
    }

    public function testUnknownSavedSearchIs404(): void
    {
        $this->assertStatus(404, 'GET', '/assets', self::USER, query: [
            'savedSearch' => '00000000-0000-4000-8000-000000000000',
        ]);
    }

    public function testStoredSortApplies(): void
    {
        $this->createAssets();

        $desc = $this->createSavedSearch(['sortBy' => [['a' => self::CITY, 'w' => 1]]]);
        $this->assertSame(['In A', 'In C', 'In B'], $this->searchNames(['savedSearch' => $desc->getId()]));

        $asc = $this->createSavedSearch(['sortBy' => [['a' => self::CITY, 'w' => 0]]]);
        $this->assertSame(['In B', 'In C', 'In A'], $this->searchNames(['savedSearch' => $asc->getId()]));
    }

    public function testRequestOrderOverridesStoredSort(): void
    {
        $this->createAssets();
        $savedSearch = $this->createSavedSearch(['sortBy' => [['a' => self::CITY, 'w' => 1]]]);

        $this->assertSame(['In B', 'In C', 'In A'], $this->searchNames([
            'savedSearch' => $savedSearch->getId(),
            'order' => [self::CITY => 'asc'],
        ]));
    }

    public function testStoredSortOnRemovedFieldIsIgnored(): void
    {
        $this->createAssets();

        $onlyRemoved = $this->createSavedSearch(['sortBy' => [['a' => 'removed', 'w' => 1, 'g' => true]]]);
        $this->assertSame(['In A', 'In B', 'In C'], $this->searchSortedNames(['savedSearch' => $onlyRemoved->getId()]));

        $withValid = $this->createSavedSearch(['sortBy' => [
            ['a' => 'removed', 'w' => 1, 'g' => true],
            ['a' => self::CITY, 'w' => 0],
        ]]);
        $this->assertSame(['In B', 'In C', 'In A'], $this->searchNames(['savedSearch' => $withValid->getId()]));
    }

    public function testStoredSortOnNonSortableFieldIsIgnored(): void
    {
        $this->createAssets();

        $onlyId = $this->createSavedSearch(['sortBy' => [['a' => '@id', 'w' => 1]]]);
        $this->assertSame(['In A', 'In B', 'In C'], $this->searchSortedNames(['savedSearch' => $onlyId->getId()]));

        $this->createAttributeDefinition([
            'workspace' => $this->createWorkspace(['slug' => 'other-workspace']),
            'name' => 'Country',
        ]);
        $onlyNonSortable = $this->createSavedSearch(['sortBy' => [['a' => 'country_text_s', 'w' => 1]]]);
        $this->assertSame(['In A', 'In B', 'In C'], $this->searchSortedNames(['savedSearch' => $onlyNonSortable->getId()]));

        $withValid = $this->createSavedSearch(['sortBy' => [
            ['a' => '@id', 'w' => 1],
            ['a' => 'country_text_s', 'w' => 1],
            ['a' => self::CITY, 'w' => 0],
        ]]);
        $this->assertSame(['In B', 'In C', 'In A'], $this->searchNames(['savedSearch' => $withValid->getId()]));

        $this->assertStatus(400, 'GET', '/assets', self::USER, query: [
            'savedSearch' => $withValid->getId(),
            'order' => ['@id' => 'asc'],
        ]);
    }

    public function testInvalidRequestOrderIsStillRejected(): void
    {
        $this->createAssets();
        $savedSearch = $this->createSavedSearch(['sortBy' => [['a' => self::CITY, 'w' => 1]]]);

        $this->assertStatus(400, 'GET', '/assets', self::USER, query: [
            'savedSearch' => $savedSearch->getId(),
            'order' => ['@createdAt' => 'sideways'],
        ]);
        $this->assertStatus(400, 'GET', '/assets', self::USER, query: [
            'savedSearch' => $savedSearch->getId(),
            'order' => ['removed' => 'asc'],
        ]);
    }

    public function testStoredQueryAppliesUnlessOneIsGiven(): void
    {
        $this->createAssets();
        $savedSearch = $this->createSavedSearch(['query' => 'Paris']);

        $this->assertSame(['In A'], $this->searchSortedNames(['savedSearch' => $savedSearch->getId()]));
        $this->assertSame(['In A'], $this->searchSortedNames(['savedSearch' => $savedSearch->getId(), 'query' => '  ']));
        $this->assertSame(['In C'], $this->searchSortedNames(['savedSearch' => $savedSearch->getId(), 'query' => 'Berlin']));
    }

    public function testRequestConditionsAreCombinedWithStoredOnes(): void
    {
        $this->createAssets();
        $savedSearch = $this->createSavedSearch([
            'conditions' => [
                ['id' => 'c1', 'query' => $this->inCollections('A', 'B')],
            ],
        ]);

        $this->assertSame(['In A', 'In B'], $this->searchSortedNames(['savedSearch' => $savedSearch->getId()]));
        $this->assertSame(['In B', 'In C'], $this->searchSortedNames(['conditions' => [$this->inCollections('B', 'C')]]));
        $this->assertSame(['In B'], $this->searchSortedNames([
            'savedSearch' => $savedSearch->getId(),
            'conditions' => [$this->inCollections('B', 'C')],
        ]));
    }

    public function testOnlyQueryConditionsAndSortAreTakenFromData(): void
    {
        $this->createAssets();
        $savedSearch = $this->createSavedSearch([
            'query' => 'Paris',
            'ids' => [$this->assets['In B']],
            'workspaces' => ['00000000-0000-4000-8000-000000000000'],
            'collection' => $this->collections['B'],
            'limit' => 0,
        ]);

        $this->assertSame(['In A'], $this->searchSortedNames(['savedSearch' => $savedSearch->getId()]));
    }
}
