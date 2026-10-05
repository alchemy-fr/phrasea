<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Attribute;

use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /metadata-tags, complements Api\MetadataTagTest: result limit, ranking, and the
 * absence of an item operation.
 */
final class MetadataTagApiTest extends AbstractDataboxTestCase
{
    use AttributeApiTestTrait;

    private function search(?string $query): array
    {
        $response = $this->api('GET', '/metadata-tags', self::USER, options: [
            'query' => null === $query ? [] : ['query' => $query],
        ]);
        $this->assertResponseStatusCodeSame(200);

        return array_column($response->toArray()['hydra:member'], 'id');
    }

    public function testWithoutQueryTheNamespacesAreLimited(): void
    {
        $ids = $this->search(null);

        $this->assertNotEmpty($ids);
        $this->assertLessThanOrEqual(50, count($ids));
        foreach ($ids as $id) {
            $this->assertStringNotContainsString(':', $id);
        }
    }

    public function testPrefixMatchesComeBeforeContainedMatches(): void
    {
        $ids = $this->search('p');

        $this->assertNotEmpty($ids);
        $prefixed = array_map(fn (string $id): bool => 0 === stripos($id, 'p'), $ids);
        $firstContained = array_search(false, $prefixed, true);
        if (false !== $firstContained) {
            $this->assertNotContains(true, array_slice($prefixed, $firstContained), 'no prefix match after a contained one');
        }
        foreach ($ids as $id) {
            $this->assertStringContainsStringIgnoringCase('p', $id);
        }
    }

    public function testAnEmptyTagQueryListsTheTagsOfTheNamespace(): void
    {
        $ids = $this->search('iptc:');

        $this->assertNotEmpty($ids);
        $this->assertLessThanOrEqual(50, count($ids));
        foreach ($ids as $id) {
            $this->assertStringStartsWith('IPTC:', $id, 'the namespace keeps the dictionary case');
        }
    }

    public function testThereIsNoItemOperation(): void
    {
        $this->api('GET', '/metadata-tags/IPTC', self::USER);
        $this->assertResponseStatusCodeSame(404);
    }
}
