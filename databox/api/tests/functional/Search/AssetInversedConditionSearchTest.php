<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * A condition can be inverted: `NOT (...)` in AQL, or `inversed` on the
 * conditions of a saved search.
 */
class AssetInversedConditionSearchTest extends AbstractSearchTestCase
{
    public function testInversedCondition(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $a = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'A',
            'public' => true,
        ]);
        $b = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'B',
            'public' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In A',
            'public' => true,
            'collectionId' => $a->getId(),
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In B',
            'public' => true,
            'collectionId' => $b->getId(),
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $inA = sprintf('@collection = "%s"', $a->getId());
        $this->assertSearch(['conditions' => [$inA]], ['In A']);
        $this->assertSearch(['conditions' => [sprintf('NOT (%s)', $inA)]], ['In B']);

        $savedSearch = new SavedSearch();
        $savedSearch->setName('Not in A');
        $savedSearch->setOwnerId('owner');
        $savedSearch->setPrivacy(SavedSearchPrivacyEnum::Private);
        $savedSearch->setData([
            'conditions' => [
                ['id' => 'c1', 'query' => $inA, 'inversed' => true],
                // Disabled conditions are ignored, inverted or not
                ['id' => 'c2', 'query' => $inA, 'disabled' => true],
            ],
        ]);
        $em = self::getEntityManager();
        $em->persist($savedSearch);
        $em->flush();

        $this->assertSearch(['savedSearch' => $savedSearch->getId()], ['In B']);
    }

    /**
     * @param array<string, mixed> $query
     * @param string[]             $expectedNames
     */
    private function assertSearch(array $query, array $expectedNames): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'query' => $query,
        ]);

        $data = $this->getDataFromResponse($response, 200)['member'];
        $names = array_map(fn (array $asset): ?string => $asset['name'] ?? null, $data);
        sort($names);

        self::assertSame($expectedNames, $names, sprintf('Unexpected results for %s', json_encode($query)));
    }
}
