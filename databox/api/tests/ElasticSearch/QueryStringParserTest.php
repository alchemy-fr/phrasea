<?php

declare(strict_types=1);

namespace App\Tests\ElasticSearch;

use App\Elasticsearch\QueryStringParser;
use PHPUnit\Framework\TestCase;

class QueryStringParserTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testParser(string $query, array $expectedMust, string $expectedShould): void
    {
        $parser = new QueryStringParser();

        $parsed = $parser->parseQuery($query);

        $this->assertEquals($expectedMust, $parsed['must']);
        $this->assertEquals($expectedShould, $parsed['should']);
    }

    public function getCases(): array
    {
        return [
            ['', [], ''],
            ['a', [], 'a'],
            ['0', [], '0'],
            ['a b', [], 'a b'],
            ['a  b', [], 'a b'],
            ['"a b"', ['a b'], ''],
            ['"a  b"', ['a b'], ''],
            ['"a  b" c', ['a b'], 'c'],
            ['0 "a  b"', ['a b'], '0'],
            ['0 "a  b" c', ['a b'], '0 c'],
        ];
    }
}
