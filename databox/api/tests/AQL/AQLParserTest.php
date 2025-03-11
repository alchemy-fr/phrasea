<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\AQLParser;
use PHPUnit\Framework\TestCase;

class AQLParserTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testParse(string $expression, array|null $expectedData): void
    {
        $parser = new AQLParser(true);
        $result = $parser->parse($expression);

        if (null !== $expectedData) {
            $this->assertNotNull($result);
            $this->assertEquals($expectedData, $result['data']);
        } else {
            $this->assertNull($result);
        }
    }

    public function getCases(): array
    {
        $fooEqualsBar = [
            'type' => 'criteria',
            'operator' => '=',
            'leftOperand' => ['field' => 'foo'],
            'rightOperand' => ['field' => 'bar'],
        ];
        $fooEquals42 = [
            'type' => 'criteria',
            'operator' => '=',
            'leftOperand' => ['field' => 'foo'],
            'rightOperand' => 42,
        ];

        return [
            ['foo', null],
            ['foo =', null],
            ['foo=', null],
            ['foo=bar', $fooEqualsBar],
            ['foo =bar', $fooEqualsBar],
            ['foo= bar', $fooEqualsBar],
            ['foo = bar', $fooEqualsBar],
            ['foo=42', $fooEquals42],
            ['foo= 42', $fooEquals42],
            ['foo =42', $fooEquals42],
            ['foo = 42', $fooEquals42],
            ['foo = bar AND foo = bar OR foo = 42', [
                'type' => 'expression',
                'operator' => 'OR',
                'conditions' => [
                    [
                        'type' => 'expression',
                        'operator' => 'AND',
                        'conditions' => [$fooEqualsBar, $fooEqualsBar],
                    ],
                    $fooEquals42,
                ],
            ]],
            ['foo = bar AND (foo = bar OR foo = 42)', [
                'type' => 'expression',
                'operator' => 'AND',
                'conditions' => [
                    $fooEqualsBar,
                    [
                        'type' => 'expression',
                        'operator' => 'OR',
                        'conditions' => [$fooEqualsBar, $fooEquals42],
                    ],
                ],
            ]],
            ['@createdAt <= "2025-01-16"', [
                'type' => 'criteria',
                'operator' => '<=',
                'leftOperand' => ['field' => '@createdAt'],
                'rightOperand' => ['literal' => '2025-01-16'],
            ]],
            ['@createdAt BETWEEN "2020-01-01" AND "2025-12-31"', [
                'type' => 'criteria',
                'operator' => 'BETWEEN',
                'leftOperand' => ['field' => '@createdAt'],
                'rightOperand' => [['literal' => '2020-01-01'], ['literal' => '2025-12-31']],
            ]],
        ];
    }
}
