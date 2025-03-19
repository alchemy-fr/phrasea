<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
use App\Elasticsearch\Facet\CreatedAtFacet;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\Facet\WorkspaceFacet;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AQLToESQueryTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testAQLToQuery(string $expression, array $expectedQuery): void
    {
        $parser = new AQLParser();
        $result = $parser->parse($expression);
        $em = $this->createMock(EntityManagerInterface::class);

        $esQueryConverter = new AQLToESQuery(new FacetRegistry([
            '@workspace' => new WorkspaceFacet($em),
            '@createdAt' => new CreatedAtFacet(),
        ]));

        $fieldClusters = [
            [
                'fields' => [
                    'attrs.{l}.foo' => [],
                    'attrs.{l}.field' => [],
                    'attrs._.number' => [],
                    'attrs._.other_number' => [],
                ],
                'w' => null
            ]

        ];

        $query = $esQueryConverter->createQuery($fieldClusters, $result['data'], [])->toArray();
        $this->assertEquals($expectedQuery, $query);
    }

    public function getCases(): array
    {
        return [
            ['foo="bar"', [
                'term' => ['attrs.*.foo' => 'bar'],
            ]],
            ['@workspace="42"', [
                'term' => ['workspaceId' => '42'],
            ]],
            ['@createdAt<="2025-01-16"', [
                'range' => ['createdAt' => [
                    'lte' => '2025-01-16',
                ]],
            ]],
            ['field IN (true, false)', [
                'terms' => ['attrs.*.field' => [
                   true, false,
                ]],
            ]],
            ['number > other_number', [
                'script' => [
                    'script' => [
                       'source' => '!doc["attrs._.number"].empty && !doc["attrs._.other_number"].empty && doc["attrs._.number"].value > doc["attrs._.other_number"].value'
                    ],
                ],
            ]],
        ];
    }
}
