<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\AQLParser;
use App\Elasticsearch\AQL\AQLToESQuery;
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
        $esQueryConverter = new AQLToESQuery();
        $query = $esQueryConverter->createQuery($result['data'])->toArray();
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
        ];
    }
}
