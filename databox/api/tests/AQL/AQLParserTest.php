<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\AQLParser;
use PHPUnit\Framework\TestCase;

class AQLParserTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testParse(string $expression, ?array $expectedData): void
    {
        $parser = new AQLParser(true);
        $result = $parser->parse($expression);

        if (null !== $expectedData) {
            $this->assertNotNull($result, 'Parse error');
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
            ['foo = (42)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => 42,
                ],
            ]],
            ['foo = 42 + 1', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'value_expression',
                    'operator' => '+',
                    'leftOperand' => 42,
                    'rightOperand' => 1,
                ],
            ]],
            ['foo = (42 + 1)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '+',
                        'leftOperand' => 42,
                        'rightOperand' => 1,
                    ],
                ],
            ]],
            ['foo = (42 + (1))', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '+',
                        'leftOperand' => 42,
                        'rightOperand' => [
                            'type' => 'parentheses',
                            'expression' => 1,
                        ],
                    ],
                ],
            ]],
            ['foo = 2 * 4 * 2 - 1', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'value_expression',
                    'operator' => '-',
                    'leftOperand' => [
                        'type' => 'value_expression',
                        'operator' => '*',
                        'leftOperand' => [
                            'type' => 'value_expression',
                            'operator' => '*',
                            'leftOperand' => 2,
                            'rightOperand' => 4,
                        ],
                        'rightOperand' => 2,
                    ],
                    'rightOperand' => 1,
                ],
            ]],
            ['foo = 3-2-1', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'value_expression',
                    'operator' => '-',
                    'leftOperand' => [
                        'type' => 'value_expression',
                        'operator' => '-',
                        'leftOperand' => 3,
                        'rightOperand' => 2,
                    ],
                    'rightOperand' => 1,
                ],
            ]],
            ['foo = 1+2+3', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'value_expression',
                    'operator' => '+',
                    'leftOperand' => [
                        'type' => 'value_expression',
                        'operator' => '+',
                        'leftOperand' => 1,
                        'rightOperand' => 2,
                    ],
                    'rightOperand' => 3,
                ],
            ]],
            ['foo = 10-8', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'value_expression',
                    'operator' => '-',
                    'leftOperand' => 10,
                    'rightOperand' => 8,
                ],
            ]],
            ['foo = (number * 42)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '*',
                        'leftOperand' => ['field' => 'number'],
                        'rightOperand' => 42,
                    ],
                ],
            ]],
            ['foo = (42 - 1)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '-',
                        'leftOperand' => 42,
                        'rightOperand' => 1,
                    ],
                ],
            ]],
            ['foo = (42 - 1 + field)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '+',
                        'leftOperand' => [
                            'type' => 'value_expression',
                            'operator' => '-',
                            'leftOperand' => 42,
                            'rightOperand' => 1,
                        ],
                        'rightOperand' => ['field' => 'field'],
                    ],
                ],
            ]],
            ['foo = (field1 - field2)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '-',
                        'leftOperand' => ['field' => 'field1'],
                        'rightOperand' => ['field' => 'field2'],
                    ],
                ],
            ]],
            ['foo = (42 - 1 - 42)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'foo'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '-',
                        'leftOperand' => [
                            'type' => 'value_expression',
                            'operator' => '-',
                            'leftOperand' => 42,
                            'rightOperand' => 1,
                        ],
                        'rightOperand' => 42,
                    ],
                ],
            ]],
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
            ['@createdAt NOT BETWEEN "2020-01-01" AND "2025-12-31"', [
                'type' => 'criteria',
                'operator' => 'NOT_BETWEEN',
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
                    ],
                ],
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
                    ],
                ],
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
            ['my_field DOES NOT START WITH "s"', [
                'type' => 'criteria',
                'operator' => 'NOT_STARTS_WITH',
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
            ['my_field DOES NOT CONTAIN "."', [
                'type' => 'criteria',
                'operator' => 'NOT_CONTAINS',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => ['literal' => '.'],
            ]],
            ['my_field MATCHES "."', [
                'type' => 'criteria',
                'operator' => 'MATCHES',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => ['literal' => '.'],
            ]],
            ['my_field DOES NOT MATCHES "."', [
                'type' => 'criteria',
                'operator' => 'NOT_MATCHES',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => ['literal' => '.'],
            ]],
            ['my_field DO NOT MATCH "."', [
                'type' => 'criteria',
                'operator' => 'NOT_MATCHES',
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
            ['number > NOW()', [
                'type' => 'criteria',
                'operator' => '>',
                'leftOperand' => ['field' => 'number'],
                'rightOperand' => [
                    'type' => 'function_call',
                    'function' => 'NOW',
                    'arguments' => [],
                ],
            ]],
            ['my_field = SUBSTRING(my_field, 42 - 2, number / 5, NOW())', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [
                    'type' => 'function_call',
                    'function' => 'SUBSTRING',
                    'arguments' => [
                        ['field' => 'my_field'],
                        [
                            'type' => 'value_expression',
                            'operator' => '-',
                            'leftOperand' => 42,
                            'rightOperand' => 2,
                        ],
                        [
                            'type' => 'value_expression',
                            'operator' => '/',
                            'leftOperand' => ['field' => 'number'],
                            'rightOperand' => 5,
                        ],
                        ['type' => 'function_call', 'function' => 'NOW', 'arguments' => []],
                    ],
                ],
            ]],
            ['my_field = now() * 2', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [
                    'type' => 'value_expression',
                    'operator' => '*',
                    'leftOperand' => [
                        'type' => 'function_call',
                        'function' => 'now',
                        'arguments' => [],
                    ],
                    'rightOperand' => 2,
                ],
            ]],
            ['my_field = (now() * 2)', [
                'type' => 'criteria',
                'operator' => '=',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [
                    'type' => 'parentheses',
                    'expression' => [
                        'type' => 'value_expression',
                        'operator' => '*',
                        'leftOperand' => [
                            'type' => 'function_call',
                            'function' => 'now',
                            'arguments' => [],
                        ],
                        'rightOperand' => 2,
                    ],
                ],
            ]],
            ['my_field WITHIN CIRCLE (48.8, 2.32, "10km")', [
                'type' => 'criteria',
                'operator' => 'WITHIN_CIRCLE',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [
                    48.8,
                    2.32,
                    ['literal' => '10km'],
                ],
            ]],
            ['my_field WITHIN RECTANGLE (1.1, 1.2, 2.1, 2.2)', [
                'type' => 'criteria',
                'operator' => 'WITHIN_RECTANGLE',
                'leftOperand' => ['field' => 'my_field'],
                'rightOperand' => [
                    1.1,
                    1.2,
                    2.1,
                    2.2,
                ],
            ]],
        ];
    }
}
