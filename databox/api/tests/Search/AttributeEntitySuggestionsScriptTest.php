<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Attribute\AttributeInterface;
use App\Consumer\Handler\Search\AttributeEntitySuggestionsScript;
use App\Elasticsearch\ElasticSearchClient;
use Ramsey\Uuid\Uuid;

/**
 * Runs the Painless scripts of the attribute entity handlers against Elasticsearch,
 * on hand-made asset documents.
 */
class AttributeEntitySuggestionsScriptTest extends AbstractSearchTest
{
    private const string DEFINITION_A = 'definition-a';
    private const string DEFINITION_B = 'definition-b';
    private const string ENTITY_1 = 'entity-1';
    private const string ENTITY_2 = 'entity-2';

    public function testRenameRelabelsEveryLocale(): void
    {
        $assetId = $this->indexAsset([
            self::entry(self::DEFINITION_A, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'en', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_B, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, '_', 'France', self::ENTITY_2),
            self::entry(self::DEFINITION_A, 'fr', 'Paris'),
        ]);

        $this->updateSuggestions($assetId, [self::ENTITY_1], self::ENTITY_1, [
            '_' => 'Deutschland',
            'fr' => 'Allemagne',
            'en' => 'Germany',
        ]);

        // Other entities and plain values are left untouched
        $this->assertSuggestions([
            self::entry(self::DEFINITION_A, '_', 'Deutschland', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Allemagne', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'en', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_B, '_', 'Deutschland', self::ENTITY_1),
            self::entry(self::DEFINITION_A, '_', 'France', self::ENTITY_2),
            self::entry(self::DEFINITION_A, 'fr', 'Paris'),
        ], $assetId);
    }

    public function testLocalesNotSuggestedAnymoreAreDropped(): void
    {
        $assetId = $this->indexAsset([
            self::entry(self::DEFINITION_A, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'en', 'Germany', self::ENTITY_1),
        ]);

        $this->updateSuggestions($assetId, [self::ENTITY_1], self::ENTITY_1, [
            '_' => 'Germany',
            'fr' => 'Allemagne',
        ]);

        $this->assertSuggestions([
            self::entry(self::DEFINITION_A, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Allemagne', self::ENTITY_1),
        ], $assetId);
    }

    public function testMergeReattachesAndDeduplicates(): void
    {
        $assetId = $this->indexAsset([
            self::entry(self::DEFINITION_A, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Allemagne', self::ENTITY_1),
            self::entry(self::DEFINITION_A, '_', 'Deutschland', self::ENTITY_2),
            self::entry(self::DEFINITION_A, 'fr', 'Allemagne', self::ENTITY_2),
            self::entry(self::DEFINITION_B, '_', 'Deutschland', self::ENTITY_2),
        ]);

        $this->updateSuggestions($assetId, [self::ENTITY_1, self::ENTITY_2], self::ENTITY_2, [
            '_' => 'Deutschland',
            'fr' => 'Allemagne',
        ]);

        $this->assertSuggestions([
            self::entry(self::DEFINITION_A, '_', 'Deutschland', self::ENTITY_2),
            self::entry(self::DEFINITION_A, 'fr', 'Allemagne', self::ENTITY_2),
            self::entry(self::DEFINITION_B, '_', 'Deutschland', self::ENTITY_2),
        ], $assetId);
    }

    public function testDeleteRemovesTheEntriesOfTheEntity(): void
    {
        $assetId = $this->indexAsset([
            self::entry(self::DEFINITION_A, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Allemagne', self::ENTITY_1),
            self::entry(self::DEFINITION_A, '_', 'France', self::ENTITY_2),
            self::entry(self::DEFINITION_A, 'fr', 'Paris'),
        ]);

        $this->updateSuggestions($assetId, [self::ENTITY_1], self::ENTITY_1, []);

        $this->assertSuggestions([
            self::entry(self::DEFINITION_A, '_', 'France', self::ENTITY_2),
            self::entry(self::DEFINITION_A, 'fr', 'Paris'),
        ], $assetId);
    }

    public function testClearedDefinitionsAreRemoved(): void
    {
        $assetId = $this->indexAsset([
            self::entry(self::DEFINITION_A, '_', 'Germany', self::ENTITY_1),
            self::entry(self::DEFINITION_A, 'fr', 'Paris'),
            self::entry(self::DEFINITION_B, '_', 'Germany', self::ENTITY_1),
        ]);

        $this->runScript($assetId, AttributeEntitySuggestionsScript::removeDefinitionsCall(), [
            '_definitionIds' => [self::DEFINITION_A],
        ]);

        $this->assertSuggestions([
            self::entry(self::DEFINITION_B, '_', 'Germany', self::ENTITY_1),
        ], $assetId);
    }

    public function testAssetsWithoutSuggestionsAreLeftUntouched(): void
    {
        $assetId = $this->indexAsset(null);

        $this->updateSuggestions($assetId, [self::ENTITY_1], self::ENTITY_1, ['_' => 'Germany']);
        $this->runScript($assetId, AttributeEntitySuggestionsScript::removeDefinitionsCall(), [
            '_definitionIds' => [self::DEFINITION_A],
        ]);

        $this->assertSuggestions(null, $assetId);
    }

    private static function entry(string $definitionId, string $locale, string $value, ?string $entityId = null): array
    {
        $entry = [
            'definitionId' => $definitionId,
            'locale' => $locale,
            'value' => $value,
        ];
        if (null !== $entityId) {
            $entry['entityId'] = $entityId;
        }

        return $entry;
    }

    private function indexAsset(?array $suggestions): string
    {
        $assetId = Uuid::uuid4()->toString();
        $this->getEsClient()->request(sprintf('%s/_doc/%s?refresh=true', $this->getAssetIndexName(), $assetId), [
            'name' => 'Painless fixture',
            AttributeInterface::SUGGESTIONS_FIELD => $suggestions,
        ], 'PUT');

        return $assetId;
    }

    /**
     * @param string[]              $entityIds
     * @param array<string, string> $labels
     */
    private function updateSuggestions(string $assetId, array $entityIds, string $entityId, array $labels): void
    {
        $this->runScript($assetId, AttributeEntitySuggestionsScript::declaration().AttributeEntitySuggestionsScript::CALL, [
            '_entityIds' => $entityIds,
            '_id' => $entityId,
            '_labels' => AttributeEntitySuggestionsScript::labels($labels),
        ]);
    }

    private function runScript(string $assetId, string $source, array $params): void
    {
        $response = $this->getEsClient()->request($this->getAssetIndexName().'/_update_by_query?conflicts=proceed&refresh=true', [
            'query' => [
                'ids' => [
                    'values' => [$assetId],
                ],
            ],
            'script' => [
                'source' => $source,
                'params' => $params,
                'lang' => 'painless',
            ],
        ])->asArray();

        $this->assertSame([], $response['failures'], 'The script must run without error');
        $this->assertSame(1, $response['updated'], 'The document must be updated');
    }

    private function assertSuggestions(?array $expected, string $assetId): void
    {
        $response = $this->getEsClient()->request($this->getAssetIndexName().'/_search', [
            'query' => [
                'ids' => [
                    'values' => [$assetId],
                ],
            ],
        ])->asArray();

        $this->assertEquals($expected, $response['hits']['hits'][0]['_source'][AttributeInterface::SUGGESTIONS_FIELD]);
    }

    private function getEsClient(): ElasticSearchClient
    {
        return self::getService(ElasticSearchClient::class);
    }

    private function getAssetIndexName(): string
    {
        return $this->getEsClient()->getIndexName('asset');
    }
}
