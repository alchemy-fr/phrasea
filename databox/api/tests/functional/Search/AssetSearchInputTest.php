<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Elasticsearch\AbstractSearch;
use App\Tests\Functional\AbstractSearchTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The inputs of the asset search that do not depend on the attributes: collection filters and pagination.
 */
class AssetSearchInputTest extends AbstractSearchTestCase
{
    private const string UNKNOWN_COLLECTION = 'fa4d6a1a-0000-4000-8000-000000000000';

    /**
     * Filtering on a collection the user cannot read would tell which of the readable assets
     * belong to it: it is answered exactly like an unknown collection.
     */
    public function testUnreadableCollectionIsAnsweredLikeAnUnknownOne(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $open = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Open',
            'public' => true,
            'no_flush' => true,
        ]);
        $secret = $this->createCollection([
            'workspace' => $workspace,
            'name' => 'Secret',
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In open',
            'public' => true,
            'collectionId' => $open->getId(),
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'In secret',
            'public' => true,
            'collectionId' => $secret->getId(),
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $openId = $open->getId();
        $secretId = $secret->getId();

        // The asset itself is readable
        $this->assertSame(['In open', 'In secret'], $this->getNames($this->search([])));
        $this->assertSame(['In open'], $this->getNames($this->search(['collection' => $openId])));

        foreach ([
            'collection' => fn (string $id): array => ['collection' => $id],
            'parents' => fn (string $id): array => ['parents' => [$id]],
            'directCollections' => fn (string $id): array => ['directCollections' => [$id]],
        ] as $filter => $query) {
            $unknown = $this->search($query(self::UNKNOWN_COLLECTION));
            $unreadable = $this->search($query($secretId));

            $this->assertSame(404, $unknown->getStatusCode(), $filter);
            $this->assertSame(404, $unreadable->getStatusCode(), $filter);
            $this->assertSame(
                str_replace(self::UNKNOWN_COLLECTION, $secretId, (string) ($unknown->toArray(false)['detail'] ?? '')),
                $unreadable->toArray(false)['detail'] ?? null,
                $filter,
            );
        }

        // Within a list, an unreadable collection is ignored like an unknown one
        $this->assertSame(['In open'], $this->getNames($this->search(['parents' => [$openId, $secretId]])));
        $this->assertSame(['In open'], $this->getNames($this->search(['directCollections' => [$openId, self::UNKNOWN_COLLECTION]])));

        foreach (['@collection', '@directCollection'] as $attribute) {
            $unknown = $this->search(['conditions' => [sprintf('%s = "%s"', $attribute, self::UNKNOWN_COLLECTION)]]);
            $unreadable = $this->search(['conditions' => [sprintf('%s = "%s"', $attribute, $secretId)]]);

            $this->assertSame(400, $unknown->getStatusCode(), $attribute);
            $this->assertSame(400, $unreadable->getStatusCode(), $attribute);
            $this->assertSame($unknown->toArray(false)['detail'] ?? '', $unreadable->toArray(false)['detail'] ?? null, $attribute);
        }
    }

    public function testPaginationIsBounded(): void
    {
        $this->createAsset([
            'workspace' => $this->createWorkspace(['public' => true, 'no_flush' => true]),
            'name' => 'Only',
            'public' => true,
        ]);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $this->assertSearchRefused(['limit' => 0], '"limit" must be a positive integer');
        $this->assertSearchRefused(['limit' => -5], '"limit" must be a positive integer');
        $this->assertSearchRefused(['page' => 0], '"page" must be a positive integer');
        $this->assertSearchRefused(['page' => -1], '"page" must be a positive integer');

        $window = sprintf('Only the first %d results can be paginated: narrow the search to reach the next ones', AbstractSearch::MAX_RESULT_WINDOW);
        $this->assertSearchRefused(['limit' => 50, 'page' => AbstractSearch::MAX_RESULT_WINDOW / 50 + 1], $window);
        // Capped to 50 per page
        $this->assertSearchRefused(['limit' => 1000, 'page' => AbstractSearch::MAX_RESULT_WINDOW / 50 + 1], $window);
        $this->assertSearchRefused(['page' => PHP_INT_MAX], $window);

        $this->assertSame([], $this->getNames($this->search(['limit' => 50, 'page' => AbstractSearch::MAX_RESULT_WINDOW / 50])));
        $this->assertSame(['Only'], $this->getNames($this->search(['limit' => 1000, 'page' => 1])));
    }

    private function assertSearchRefused(array $query, string $detail): void
    {
        $response = $this->search($query);
        $this->assertSame(400, $response->getStatusCode(), json_encode($query));
        $this->assertSame($detail, $response->toArray(false)['detail'] ?? null, json_encode($query));
    }

    private function search(array $query): ResponseInterface
    {
        self::getEntityManager()->clear();

        return static::createClient()->request('GET', '/assets', [
            'query' => $query,
        ]);
    }

    /**
     * @return string[]
     */
    private function getNames(ResponseInterface $response): array
    {
        $names = array_map(fn (array $asset): ?string => $asset['name'] ?? null, $this->getDataFromResponse($response, 200)['member']);
        sort($names);

        return $names;
    }
}
