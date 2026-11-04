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

        return array_column($response->toArray()['member'], 'id');
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
        $this->assertPrefixMatchesFirst('p', $ids);
        foreach ($ids as $id) {
            $this->assertStringNotContainsString(':', $id, 'a single character only matches namespaces');
        }
    }

    public function testTagsComeAfterTheNamespacesWithinTheLimit(): void
    {
        $ids = $this->search('ipt');

        $this->assertCount(50, $ids);
        $namespaces = array_values(array_filter($ids, fn (string $id): bool => !str_contains($id, ':')));
        $this->assertNotEmpty($namespaces);
        $this->assertSame($namespaces, array_slice($ids, 0, count($namespaces)));
        $this->assertPrefixMatchesFirst('ipt', $namespaces);

        $tagNames = array_map(fn (string $id): string => explode(':', $id, 2)[1], array_slice($ids, count($namespaces)));
        $this->assertNotEmpty($tagNames);
        $this->assertPrefixMatchesFirst('ipt', $tagNames);
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

    /**
     * @param string[] $values
     */
    private function assertPrefixMatchesFirst(string $query, array $values): void
    {
        $prefixed = array_map(fn (string $value): bool => 0 === stripos($value, $query), $values);
        $firstContained = array_search(false, $prefixed, true);
        if (false !== $firstContained) {
            $this->assertNotContains(true, array_slice($prefixed, $firstContained), 'no prefix match after a contained one');
        }
        foreach ($values as $value) {
            $this->assertStringContainsStringIgnoringCase($query, $value);
        }
    }
}
