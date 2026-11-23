<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\Type\BooleanAttributeType;
use App\Attribute\Type\DateAttributeType;
use App\Attribute\Type\KeywordAttributeType;
use App\Attribute\Type\NumberAttributeType;
use App\Attribute\Type\TextAttributeType;
use App\Elasticsearch\AQL\DocumentTextAnalyzer;
use App\Entity\Core\Asset;
use App\Tests\Functional\AbstractSearchTestCase;
use Elastica\Index;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The attribute filter rules give the same assets in the search (Elasticsearch query) and when
 * reading each asset (PHP evaluation in AssetVoter::READ).
 *
 * Left out: MATCHES on the translated text fields, whose language analyzers (stemming, stop words)
 * the PHP evaluation does not reproduce: it denies the assets having such values instead.
 */
class AttributeFilterRuleParityTest extends AbstractSearchTestCase
{
    #[DataProvider('getConditions')]
    public function testSearchAndReadGiveTheSameAssets(string $condition): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $parent = $this->createCollection(['workspace' => $workspace, 'name' => 'Parent']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $parent]);
        $other = $this->createCollection(['workspace' => $workspace, 'name' => 'Other']);

        $definitions = [];
        foreach ([
            'category' => [KeywordAttributeType::NAME, false, false],
            'keywords' => [KeywordAttributeType::NAME, true, false],
            'title' => [TextAttributeType::NAME, false, false],
            'label' => [TextAttributeType::NAME, false, true],
            'count' => [NumberAttributeType::NAME, false, false],
            'day' => [DateAttributeType::NAME, false, false],
            'flag' => [BooleanAttributeType::NAME, false, false],
        ] as $slug => [$type, $multiple, $translatable]) {
            $definitions[$slug] = $this->createAttributeDefinition([
                'workspace' => $workspace,
                'name' => ucfirst($slug),
                'slug' => $slug,
                'type' => $type,
                'multiple' => $multiple,
                'translatable' => $translatable,
            ]);
        }

        $assets = [
            'A' => [
                'collection' => $child,
                'tags' => ['red'],
                'values' => [
                    'category' => 'press',
                    'keywords' => ['a', 'b'],
                    'title' => 'Le Château de Paris, aujourd\'hui',
                    'label' => ['fr' => 'Bonjour', 'en' => 'Hello'],
                    'count' => 12,
                    'day' => '2021-03-04',
                    'flag' => true,
                ],
            ],
            'B' => [
                'collection' => $parent,
                'tags' => ['blue', 'red'],
                'values' => [
                    'category' => 'internal',
                    'keywords' => ['b'],
                    'title' => 'Summer in Lyon: v1.2_beta',
                    'label' => ['en' => 'Hello'],
                    'count' => 3,
                    'day' => '2019-12-31',
                    'flag' => false,
                ],
            ],
            'C' => [
                'collection' => $other,
                'tags' => [],
                'values' => [
                    'category' => 'News',
                    'count' => 25,
                ],
            ],
            'D' => [
                'collection' => $other,
                'tags' => ['blue'],
                'values' => [],
            ],
        ];

        foreach ($assets as $name => $config) {
            $attributes = [];
            foreach ($config['values'] as $slug => $value) {
                $definition = $definitions[$slug];
                if (is_array($value)) {
                    foreach ($value as $key => $v) {
                        $attributes[] = [
                            'definition' => $definition,
                            'value' => $v,
                            'locale' => is_string($key) ? $key : null,
                            'position' => is_int($key) ? $key : 0,
                        ];
                    }
                } else {
                    $attributes[] = ['definition' => $definition, 'value' => $value];
                }
            }

            $this->createAsset([
                'workspace' => $workspace,
                'name' => $name,
                'public' => true,
                'collectionId' => $config['collection']->getId(),
                'tags' => $config['tags'],
                'attributes' => $attributes,
            ]);
        }

        $condition = strtr($condition, [
            '{parent}' => $parent->getId(),
            '{child}' => $child->getId(),
            '{red}' => $this->findOrCreateTagEntity('red', $workspace)->getId(),
            '{blue}' => $this->findOrCreateTagEntity('blue', $workspace)->getId(),
        ]);
        self::getAttributeFilterManager()->saveRule($workspace, [KeycloakClientTestMock::USER_UID], [], $condition);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $headers = [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
        ];
        $client = static::createClient();

        $data = $this->getDataFromResponse($client->request('GET', '/assets', ['headers' => $headers]), 200)['member'];
        $searched = array_map(static fn (array $asset): string => $asset['name'], $data);
        sort($searched);

        $read = [];
        foreach (self::getEntityManager()->getRepository(Asset::class)->findBy(['workspace' => $workspace->getId()]) as $asset) {
            $response = $client->request('GET', '/assets/'.$asset->getId(), ['headers' => $headers]);
            if (200 === $response->getStatusCode()) {
                $read[] = $asset->name;
            }
        }
        sort($read);

        $this->assertSame($searched, $read, $condition);
    }

    public static function getConditions(): iterable
    {
        foreach ([
            'category = "press"',
            'category != "press"',
            'category IN ("press", "News")',
            'category NOT IN ("press")',
            'category IS MISSING',
            'category CONTAINS "ES"',
            'category CONTAINS "ES" CASE SENSITIVE',
            'category STARTS WITH "n"',
            'category MATCHES "news"',
            'category CONTAINS "'.str_repeat('a', 101).'"',
            'keywords = "b"',
            'keywords HAS ALL OF ("a", "b")',
            'title MATCHES "chateau"',
            'title MATCHES "paris chateau"',
            'title MATCHES "aujourdhui"',
            'title MATCHES "hui"',
            'title MATCHES "v1"',
            'title MATCHES "beta lyon"',
            'title MATCHES "lyon:"',
            'title DOES NOT MATCH "summer"',
            'title CONTAINS "in"',
            'title CONTAINS "CHÂ"',
            'title CONTAINS "CHâ"',
            'title DOES NOT CONTAIN "CHÂ"',
            'title STARTS WITH "le ch"',
            'title IS NOT EMPTY',
            'label = "Hello"',
            'label ENDS WITH "jour"',
            'count > 10',
            'count > 11.5',
            'count = 12.5',
            'count <= 12',
            'count BETWEEN 3 AND 12',
            'count NOT BETWEEN 3 AND 12',
            'day >= "2020-01-01"',
            'day <= "2019-12-31"',
            'day > "2019-12-31"',
            'day = "2021"',
            'day = "2021-03"',
            'flag = true',
            'flag = null',
            'flag != false',
            'tags = "{red}"',
            'tags = "{red}" AND tags NOT IN ("{blue}")',
            'tags MATCHES "red"',
            'tags IS MISSING',
            '@collection = "{parent}"',
            '@directCollection = "{parent}"',
            '@collection = "{child}" OR count > 20',
            'NOT (category = "press" OR count > 20)',
            '@privacy = 5',
            '@assetStatus = 0',
            '@deleted = false',
            'count > count - 1',
            'NOT (category = CONCAT(SUBSTRING(category, 0, 2), "ess"))',
            'unknown_field = "x"',
        ] as $condition) {
            yield $condition => [$condition];
        }
    }

    /**
     * The terms of the analyzers of the asset index, as Elasticsearch and the PHP evaluation give them.
     */
    #[DataProvider('getAnalyzedValues')]
    public function testAnalyzersGiveTheTermsOfElasticsearch(string $analyzer, string $value): void
    {
        self::bootKernel();
        /** @var Index $index */
        $index = self::getContainer()->get('fos_elastica.index.asset');
        $expected = array_values(array_unique(array_map(
            static fn (array $token): string => $token['token'],
            $index->analyze(['analyzer' => $analyzer, 'text' => $value]),
        )));
        sort($expected);

        $terms = array_map(strval(...), array_keys(DocumentTextAnalyzer::indexTerms($analyzer, [$value])));
        sort($terms);

        $this->assertSame($expected, $terms, $value);
    }

    public static function getAnalyzedValues(): iterable
    {
        foreach ([
            'Le Château de Paris',
            'L\'été à Paris, aujourd\'hui',
            'Qu’il fît beau',
            'John\'s car isn\'t red',
            'v1.2_beta release-candidate',
            'foo.bar1 3.14 1,000 a:b __x x_',
            'ŒUVRES complètes ÆON Øresund straße Łódź',
            'Résumé final-2024.pdf',
            'e-mail: someone@example.com « quoted » — done…',
            'ABC123def 12ab',
            '',
        ] as $value) {
            foreach ([DocumentTextAnalyzer::TEXT, DocumentTextAnalyzer::STANDARD] as $analyzer) {
                yield $analyzer.': '.$value => [$analyzer, $value];
            }
        }
    }
}
