<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Attribute\Type\DateAttributeType;
use DateTimeImmutable;

class AssetSearchTest extends AbstractSearchTest
{
    public function testLocalizedQuery(): void
    {
        $textDefinition = $this->createAttributeDefinition([
            'name' => 'Description',
            'translatable' => true,
            'no_flush' => true,
        ]);
        $multiValuedDefinition = $this->createAttributeDefinition([
            'name' => 'Keywords',
            'translatable' => true,
            'multiple' => true,
            'no_flush' => true,
        ]);
        $dateDefinition = $this->createAttributeDefinition([
            'name' => 'Date',
            'type' => DateAttributeType::getName(),
            'no_flush' => true,
        ]);

        $this->createAsset([
            'title' => 'FR',
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
                    'value' => new DateTimeImmutable('2021-11-29'),
                ],
            ],
            'no_flush' => true,
        ]);

        $this->createAsset([
            'title' => 'EN',
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
                    'value' => new DateTimeImmutable('2009-05-19'),
                ],
            ],
            'no_flush' => true,
        ]);

        $this->createAsset([
            'title' => 'AR',
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
                    'value' => new DateTimeImmutable('2008-07-12'),
                ],
            ],
            'no_flush' => true,
        ]);

        $this->getEntityManager()->flush();

        AssetSearchTest::releaseIndex();

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

    private function assertSearchResults(string $queryString, array $expectedResults): void
    {
        $response = $this->request(
            null,
            'GET',
            '/assets?query='.urlencode($queryString)
        );

        $getMessage = function (string $m) use ($queryString, $expectedResults): string {
            return sprintf('%s [case "%s", ["%s"]]',
                $m,
                $queryString,
                implode('", "', $expectedResults)
            );
        };

        $data = $this->getDataFromResponse($response, 200);
        $this->assertEquals(count($expectedResults), count($data), $getMessage('Invalid result count'));
        foreach ($expectedResults as $expectedResult) {
            $r = array_shift($data);
            $this->assertEquals($expectedResult, $r['title'], $getMessage('Invalid result order'));
        }
    }
}
