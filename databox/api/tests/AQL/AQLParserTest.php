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
            [' foo = bar AND (foo = bar OR foo = 42)', [
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
            ['foo = bar AND ((foo = bar OR foo = 42))', [
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
            ['(  ( foo = bar) AND (( foo = bar OR  foo =  42)))', [
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
            ['my_field IN ("2020-01-01")', [
                'type' => 'criteria',
                'operator' => 'IN',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [['literal' => '2020-01-01']],
            ]],
            ['my_field IN ("2020-01-01", true, 42)', [
                'type' => 'criteria',
                'operator' => 'IN',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [['literal' => '2020-01-01'], true, 42],
            ]],
            ['my_field IN ()', null],
            ['my_field NOT IN ()', null],
            ['my_field NOTIN ()', null],
            ['my_field NOTIN (1)', null],
            ['my_field IN (true) AND second_field IN (false)', [
                'type' => 'expression',
                'operator' => 'AND',
                'conditions' => [
                    [
                        'type' => 'criteria',
                        'operator' => 'IN',
                        'leftOperand' => ['field' => 'my_field'],
                        'rightOperand' => [true],
                    ],
                    [
                        'type' => 'criteria',
                        'operator' => 'IN',
                        'leftOperand' => ['field' => 'second_field'],
                        'rightOperand' => [false],
                    ]
                ]
            ]],
            ['my_field NOT IN (true) AND second_field IN (false)', [
                'type' => 'expression',
                'operator' => 'AND',
                'conditions' => [
                    [
                        'type' => 'criteria',
                        'operator' => 'NOT_IN',
                        'leftOperand' => ['field' => 'my_field'],
                        'rightOperand' => [true],
                    ],
                    [
                        'type' => 'criteria',
                        'operator' => 'IN',
                        'leftOperand' => ['field' => 'second_field'],
                        'rightOperand' => [false],
                    ]
                ]
            ]],
            ['my_field ISMISSING', null],
            ['my_field STARTSWITH', null],
            ['my_field STARTS WITH', null],
            ['my_field STARTSWITH "s"', null],
            ['my_field STARTS WITH "s"', [
                'type' => 'criteria',
                'operator' => 'STARTS_WITH',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => ['literal' => 's'],
            ]],
            ['my_field IS MISSING', [
                'type' => 'criteria',
                'operator' => 'MISSING',
                'leftOperand' => ['field' => 'my_field'],
            ]],
            ['my_field CONTAINS "."', [
                'type' => 'criteria',
                'operator' => 'CONTAINS',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => ['literal' => '.'],
            ]],
            ['my_field MATCHES "."', [
                'type' => 'criteria',
                'operator' => 'MATCHES',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => ['literal' => '.'],
            ]],
            ['@tag IN ("c333940d-9e5c-4f3c-b16a-77f8daabca87", "6ee44526-3e8e-4412-8a9b-44b82fdce6bc")', [
                'type' => 'criteria',
                'operator' => 'IN',
                'leftOperand' => ['field' => '@tag'],
                'rightOperand' => [['literal' => 'c333940d-9e5c-4f3c-b16a-77f8daabca87'], ['literal' => '6ee44526-3e8e-4412-8a9b-44b82fdce6bc']],
            ]],
            ['@tag IN ( "c333940d-9e5c-4f3c-b16a-77f8daabca87","6ee44526-3e8e-4412-8a9b-44b82fdce6bc" )', [
                'type' => 'criteria',
                'operator' => 'IN',
                'leftOperand' => ['field' => '@tag'],
                'rightOperand' => [['literal' => 'c333940d-9e5c-4f3c-b16a-77f8daabca87'], ['literal' => '6ee44526-3e8e-4412-8a9b-44b82fdce6bc']],
            ]],
            ['number > other_number', [
                'type' => 'criteria',
                'operator' => '>',
                'leftOperand' => ['field' => 'number'],
                'rightOperand' => ['field' => 'other_number'],
            ]],
        ];
    }
}
