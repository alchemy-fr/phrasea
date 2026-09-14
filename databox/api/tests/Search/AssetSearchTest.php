<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Attribute\Type\DateTimeAttributeType;

class AssetSearchTest extends AbstractSearchTest
{
    public function testAssetSearchInPublicWorkspace(): void
    {
        $workspace = $this->createWorkspace([
            'no_acl' => true,
            'public' => true,
            'no_flush' => true,
        ]);
        $this->createAsset([
            'public' => true,
            'workspace' => $workspace,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets');
        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(1, $data);
    }

    public function testAssetSearchInPrivateWorkspace(): void
    {
        $workspace = $this->createWorkspace([
            'no_acl' => true,
            'no_flush' => true,
        ]);
        $this->createAsset([
            'public' => true,
            'workspace' => $workspace,
        ]);
        self::releaseIndex();

        $client = self::createClient();
        $response = $client->request('GET', '/assets');

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertCount(0, $data);
    }

    public function testLocalizedQuery(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $textDefinition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Description',
            'translatable' => true,
            'no_flush' => true,
        ]);
        $multiValuedDefinition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Keywords',
            'translatable' => true,
            'multiple' => true,
            'no_flush' => true,
        ]);
        $dateDefinition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Date',
            'type' => DateTimeAttributeType::getName(),
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'FR',
            'public' => true,
            'attributes' => [
                [
                    'definition' => $textDefinition,
                    'locale' => 'fr',
                    'value' => 'The suis Phraseanet',
                ],
                [
                    'definition' => $multiValuedDefinition,
                    'locale' => 'fr',
                    'value' => 'avion',
                ],
                [
                    'definition' => $multiValuedDefinition,
                    'locale' => 'fr',
                    'value' => 'ananas',
                ],
                [
                    'definition' => $dateDefinition,
                    'value' => new \DateTimeImmutable('2021-11-29T13:15:19'),
                ],
            ],
            'no_flush' => true,
        ]);

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'EN',
            'public' => true,
            'attributes' => [
                [
                    'definition' => $textDefinition,
                    'locale' => 'en',
                    'value' => 'The suis Phraseanet Phraseanet',
                ],
                [
                    'definition' => $multiValuedDefinition,
                    'locale' => 'en',
                    'value' => 'plane',
                ],
                [
                    'definition' => $multiValuedDefinition,
                    'locale' => 'en',
                    'value' => 'pineapple',
                ],
                [
                    'definition' => $dateDefinition,
                    'value' => new \DateTimeImmutable('2009-05-19T13:15:19'),
                ],
            ],
            'no_flush' => true,
        ]);

        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'AR',
            'public' => true,
            'attributes' => [
                [
                    'definition' => $textDefinition,
                    'locale' => 'ar',
                    'value' => 'أنا سمة عربية',
                ],
                [
                    'definition' => $multiValuedDefinition,
                    'locale' => 'ar',
                    'value' => 'مطار',
                ],
                [
                    'definition' => $multiValuedDefinition,
                    'locale' => 'ar',
                    'value' => 'أناناس',
                ],
                [
                    'definition' => $dateDefinition,
                    'value' => new \DateTimeImmutable('2008-07-12'),
                ],
            ],
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        foreach ($this->getSearchCases() as $args) {
            $this->assertSearchResults(...$args);
        }
    }

    private static function releaseIndex(): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');
    }

    public function getSearchCases(): array
    {
        return [
            ['Phraseanet', ['EN', 'FR']],
            ['phraseanet', ['EN', 'FR']],
            ['the', ['FR']],
            ['suis', ['EN']],
            ['2009', ['EN']],
            ['2021', ['FR']],
            ['عربية', ['AR']],
            ['2008', ['AR']],
            ['2020', []],
            ['ananas', ['FR']],
            ['pineappl', ['EN']],
            ['أناناس', ['AR']],
        ];
    }

    /**
     * An attribute definition holding no value at all has no leaf in the ES mapping,
     * because those leaves are only created by dynamic templates once a document
     * actually carries a value. Sorting on it used to make Elasticsearch reject the
     * whole query ("No mapping found for [attrs._.city_text_s.raw] in order to sort on").
     */
    public function testSortOnDefinitionWithoutAnyValue(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'City',
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Lonely',
            'public' => true,
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $this->assertSortedNames(['city_text_s' => 'asc'], ['Lonely']);
    }

    /**
     * Same as above for a multi-valued definition: the leaf is suffixed "_m",
     * which is the exact field of the reported error (attrs._.city_text_m.raw).
     */
    public function testSortOnMultipleDefinitionWithoutAnyValue(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'City',
            'multiple' => true,
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Lonely',
            'public' => true,
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $this->assertSortedNames(['city_text_m' => 'asc'], ['Lonely']);
    }

    public function testSortOnDefinitionWithValuesStillOrders(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $city = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'City',
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();

        foreach ([
            'PARIS' => 'Paris',
            'AMSTERDAM' => 'Amsterdam',
        ] as $name => $value) {
            $this->createAsset([
                'workspace' => $workspace,
                'name' => $name,
                'public' => true,
                'attributes' => [
                    [
                        'definition' => $city,
                        'value' => $value,
                    ],
                ],
                'no_flush' => true,
            ]);
        }
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $this->assertSortedNames(['city_text_s' => 'asc'], ['AMSTERDAM', 'PARIS']);
        $this->assertSortedNames(['city_text_s' => 'desc'], ['PARIS', 'AMSTERDAM']);
    }

    /**
     * Built-in fields are root index properties and must keep a bare sort clause:
     * Elasticsearch rejects any option (such as unmapped_type) on a "_score" sort.
     */
    public function testSortOnBuiltInField(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Lonely',
            'public' => true,
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        foreach ($this->getBuiltInSortCases() as [$field, $way]) {
            $this->assertSortedNames([$field => $way], ['Lonely']);
        }
    }

    public function getBuiltInSortCases(): array
    {
        return [
            ['@score', 'desc'],
            ['@createdAt', 'desc'],
            ['@createdAt', 'asc'],
        ];
    }

    public function testSortOnNeverDefinedAttributeSlug(): void
    {
        $workspace = $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
        $this->createAsset([
            'workspace' => $workspace,
            'name' => 'Lonely',
            'public' => true,
            'no_flush' => true,
        ]);
        $this->getEntityManager()->flush();
        self::releaseIndex();

        $this->assertSortedNames(['ghost_text_s' => 'asc'], ['Lonely']);
    }

    /**
     * @param array<string, string> $order
     * @param list<string>          $expectedNames
     */
    private function assertSortedNames(array $order, array $expectedNames): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'query' => [
                'order' => $order,
            ],
        ]);

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];

        $this->assertSame($expectedNames, array_map(
            fn (array $r): ?string => $r['name'] ?? null,
            $data
        ), sprintf('Invalid result order for sort %s', json_encode($order)));
    }

    private function assertSearchResults(string $queryString, array $expectedResults): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/assets', [
            'query' => [
                'query' => $queryString,
            ],
        ]);

        $getMessage = fn (string $m): string => sprintf('%s [case "%s", ["%s"]]',
            $m,
            $queryString,
            implode('", "', $expectedResults)
        );

        $data = $this->getDataFromResponse($response, 200)['hydra:member'];
        $this->assertSameSize($expectedResults, $data, $getMessage('Invalid result count'));
        foreach ($expectedResults as $expectedResult) {
            $r = array_shift($data);
            $this->assertEquals($expectedResult, $r['name'] ?? null, $getMessage('Invalid result order'));
        }
    }
}
