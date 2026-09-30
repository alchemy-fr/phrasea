import {parseAQLQuery} from './AQL.ts';
import {astToString} from './query.ts';
import {AQLQueryAST} from './aqlTypes.ts';

it('parse AQL', function () {
    const dataSet = [
        {
            query: '@tag IS true',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: true,
                },
            },
        },
        {
            query: '@tag IS false',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: false,
                },
            },
        },
        {
            query: '@tag IS null',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: null,
                },
            },
        },
        {
            query: '@tag IS (1 + 2 )',
            formattedQuery: '@tag IS (1 + 2)',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: {
                        type: 'parentheses',
                        expression: {
                            type: 'value_expression',
                            operator: '+',
                            leftOperand: 1,
                            rightOperand: 2,
                        },
                    },
                },
            },
        },
        {
            query: 'field1 > NOW()',
            result: {
                expression: {
                    leftOperand: {field: 'field1'},
                    operator: '>',
                    rightOperand: {
                        type: 'function_call',
                        function: 'NOW',
                        arguments: [],
                    },
                },
            },
        },
        {
            query: 'field1 > 4 + 8 - SUBSTRING("foo", 1, 2)',
            result: {
                expression: {
                    leftOperand: {field: 'field1'},
                    operator: '>',
                    rightOperand: {
                        type: 'value_expression',
                        operator: '-',
                        leftOperand: {
                            type: 'value_expression',
                            operator: '+',
                            leftOperand: 4,
                            rightOperand: 8,
                        },
                        rightOperand: {
                            type: 'function_call',
                            function: 'SUBSTRING',
                            arguments: [{literal: 'foo'}, 1, 2],
                        },
                    },
                },
            },
        },
        {
            query: 'field1 IS "foo" AND price > 42',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {
                            leftOperand: {field: 'field1'},
                            operator: '=',
                            rightOperand: {literal: 'foo'},
                        },
                        {
                            leftOperand: {field: 'price'},
                            operator: '>',
                            rightOperand: 42,
                        },
                    ],
                },
            },
        },
        {
            query: '@createdAt IS NOT "foo"',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: '!=',
                    rightOperand: {literal: 'foo'},
                },
            },
        },
        {
            query: '@createdAt IS NOT "f\\"oo"',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: '!=',
                    rightOperand: {literal: 'f"oo'},
                },
            },
        },
        {
            query: '@createdAt IS NOT "f\\"oo\\""',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: '!=',
                    rightOperand: {literal: 'f"oo"'},
                },
            },
        },
        {
            query: '@createdAt IS NOT "fo"o"',
            result: undefined,
        },
        {
            query: '@createdAt BETWEEN 1 AND 2',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: 'BETWEEN',
                    rightOperand: [1, 2],
                },
            },
        },
        {
            query: '@createdAt NOT  BETWEEN 1 AND 2',
            formattedQuery: '@createdAt NOT BETWEEN 1 AND 2',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: 'NOT_BETWEEN',
                    rightOperand: [1, 2],
                },
            },
        },
        {
            query: ' @createdAt NOT  BETWEEN 1 AND 2 ',
            formattedQuery: '@createdAt NOT BETWEEN 1 AND 2',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: 'NOT_BETWEEN',
                    rightOperand: [1, 2],
                },
            },
        },
        {
            query: '@tag IS ANY OF ( "c333940d-9e5c-4f3c-b16a-77f8daabca87","6ee44526-3e8e-4412-8a9b-44b82fdce6bc" )',
            formattedQuery:
                '@tag IS ANY OF ("c333940d-9e5c-4f3c-b16a-77f8daabca87", "6ee44526-3e8e-4412-8a9b-44b82fdce6bc")',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: 'IN',
                    rightOperand: [
                        {literal: 'c333940d-9e5c-4f3c-b16a-77f8daabca87'},
                        {literal: '6ee44526-3e8e-4412-8a9b-44b82fdce6bc'},
                    ],
                },
            },
        },
        {
            query: '@tag IS ANY OF (33, 22)',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: 'IN',
                    rightOperand: [33, 22],
                },
            },
        },
        {
            query: '@tag IS NONE OF (true)',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: 'NOT_IN',
                    rightOperand: [true],
                },
            },
        },
        {
            query: 'description CONTAINS "foo"',
            result: {
                expression: {
                    leftOperand: {field: 'description'},
                    operator: 'CONTAINS',
                    rightOperand: {literal: 'foo'},
                },
            },
        },
        {
            query: 'description CONTAINS "Foo Bar" CASE SENSITIVE',
            result: {
                expression: {
                    leftOperand: {field: 'description'},
                    operator: 'CONTAINS',
                    rightOperand: {literal: 'Foo Bar'},
                    caseSensitive: true,
                },
            },
        },
        {
            query: 'title DOES NOT START WITH "A" CASE SENSITIVE AND f IS 1',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {
                            leftOperand: {field: 'title'},
                            operator: 'NOT_STARTS_WITH',
                            rightOperand: {literal: 'A'},
                            caseSensitive: true,
                        },
                        {
                            leftOperand: {field: 'f'},
                            operator: '=',
                            rightOperand: 1,
                        },
                    ],
                },
            },
        },
        {
            query: 'description MATCHES "foo" CASE SENSITIVE',
            result: undefined,
        },
        {
            query: 'description IS "foo" CASE SENSITIVE',
            result: undefined,
        },
        {
            query: 'number > other_number',
            result: {
                expression: {
                    leftOperand: {field: 'number'},
                    operator: '>',
                    rightOperand: {field: 'other_number'},
                },
            },
        },
        {
            query: '(f1 IS "1" AND f2 IS NOT "2") AND f3 IS NOT "3"',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {
                            operator: 'AND',
                            conditions: [
                                {
                                    leftOperand: {field: 'f1'},
                                    operator: '=',
                                    rightOperand: {literal: '1'},
                                },
                                {
                                    leftOperand: {field: 'f2'},
                                    operator: '!=',
                                    rightOperand: {literal: '2'},
                                },
                            ],
                        },
                        {
                            leftOperand: {field: 'f3'},
                            operator: '!=',
                            rightOperand: {literal: '3'},
                        },
                    ],
                },
            },
        },
        {
            query: '(f1 IS "1" AND f2 IS NOT "2") OR f3 IS NOT "3"',
            result: {
                expression: {
                    operator: 'OR',
                    conditions: [
                        {
                            operator: 'AND',
                            conditions: [
                                {
                                    leftOperand: {field: 'f1'},
                                    operator: '=',
                                    rightOperand: {literal: '1'},
                                },
                                {
                                    leftOperand: {field: 'f2'},
                                    operator: '!=',
                                    rightOperand: {literal: '2'},
                                },
                            ],
                        },
                        {
                            leftOperand: {field: 'f3'},
                            operator: '!=',
                            rightOperand: {literal: '3'},
                        },
                    ],
                },
            },
        },
        {
            query: 'f1 IS "1" AND (f2 IS NOT "2" AND f3 IS NOT "3")',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {
                            leftOperand: {field: 'f1'},
                            operator: '=',
                            rightOperand: {literal: '1'},
                        },
                        {
                            operator: 'AND',
                            conditions: [
                                {
                                    leftOperand: {field: 'f2'},
                                    operator: '!=',
                                    rightOperand: {literal: '2'},
                                },
                                {
                                    leftOperand: {field: 'f3'},
                                    operator: '!=',
                                    rightOperand: {literal: '3'},
                                },
                            ],
                        },
                    ],
                },
            },
        },
        {
            query: 'f1 IS "1" AND (f2 IS NOT "2" OR f3 IS NOT "3")',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {
                            leftOperand: {field: 'f1'},
                            operator: '=',
                            rightOperand: {literal: '1'},
                        },
                        {
                            operator: 'OR',
                            conditions: [
                                {
                                    leftOperand: {field: 'f2'},
                                    operator: '!=',
                                    rightOperand: {literal: '2'},
                                },
                                {
                                    leftOperand: {field: 'f3'},
                                    operator: '!=',
                                    rightOperand: {literal: '3'},
                                },
                            ],
                        },
                    ],
                },
            },
        },
        {
            query: 'f1 IS "1" OR (f2 IS NOT "2" AND f3 IS NOT "3")',
            result: {
                expression: {
                    operator: 'OR',
                    conditions: [
                        {
                            leftOperand: {field: 'f1'},
                            operator: '=',
                            rightOperand: {literal: '1'},
                        },
                        {
                            operator: 'AND',
                            conditions: [
                                {
                                    leftOperand: {field: 'f2'},
                                    operator: '!=',
                                    rightOperand: {literal: '2'},
                                },
                                {
                                    leftOperand: {field: 'f3'},
                                    operator: '!=',
                                    rightOperand: {literal: '3'},
                                },
                            ],
                        },
                    ],
                },
            },
        },
        {
            query: 'location WITHIN CIRCLE (48.8, 2.32, "10km")',
            result: {
                expression: {
                    leftOperand: {field: 'location'},
                    operator: 'WITHIN_CIRCLE',
                    rightOperand: [48.8, 2.32, {literal: '10km'}],
                },
            },
        },
        {
            query: 'b IS ANY OF (true, false)',
            result: {
                expression: {
                    leftOperand: {field: 'b'},
                    operator: 'IN',
                    rightOperand: [true, false],
                },
            },
        },
    ];

    dataSet.forEach(({query, result, formattedQuery}) => {
        const actual = parseAQLQuery(query);
        expect(actual).toEqual(result);
        if (result !== undefined) {
            expect(astToString(result as AQLQueryAST)).toEqual(
                formattedQuery ?? query
            );
        }
    });
});

it('parse AQL new operators', function () {
    const dataSet = [
        {
            query: 'title ENDS WITH "foo"',
            result: {
                expression: {
                    leftOperand: {field: 'title'},
                    operator: 'ENDS_WITH',
                    rightOperand: {literal: 'foo'},
                },
            },
        },
        {
            query: 'title DOES NOT END WITH "Foo" CASE SENSITIVE',
            result: {
                expression: {
                    leftOperand: {field: 'title'},
                    operator: 'NOT_ENDS_WITH',
                    rightOperand: {literal: 'Foo'},
                    caseSensitive: true,
                },
            },
        },
        {
            query: '@tag HAS ALL OF ("a", "b")',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: 'HAS_ALL_OF',
                    rightOperand: [{literal: 'a'}, {literal: 'b'}],
                },
            },
        },
        {
            query: 'title IS EMPTY',
            result: {
                expression: {
                    leftOperand: {field: 'title'},
                    operator: 'MISSING',
                },
            },
        },
        {
            query: 'title IS NOT EMPTY',
            result: {
                expression: {
                    leftOperand: {field: 'title'},
                    operator: 'EXISTS',
                },
            },
        },
        {
            query: 'title IS other_field',
            result: {
                expression: {
                    leftOperand: {field: 'title'},
                    operator: '=',
                    rightOperand: {field: 'other_field'},
                },
            },
        },
        {
            query: 'title IS ANY OF "a"',
            result: undefined,
        },
        {
            query: 'title HAS ALL OF',
            result: undefined,
        },
    ];

    dataSet.forEach(({query, result}) => {
        const actual = parseAQLQuery(query);
        expect(actual).toEqual(result);
        if (result !== undefined) {
            expect(astToString(result as AQLQueryAST)).toEqual(query);
        }
    });
});

it('parse AQL operator aliases', function () {
    const aliases: [string, string][] = [
        ['f = "a"', 'f IS "a"'],
        ['f != "a"', 'f IS NOT "a"'],
        ['f IN ("a", "b")', 'f IS ANY OF ("a", "b")'],
        ['f HAS ANY OF ("a", "b")', 'f IS ANY OF ("a", "b")'],
        ['f NOT IN ("a")', 'f IS NONE OF ("a")'],
        ['f HAS NONE OF ("a")', 'f IS NONE OF ("a")'],
        ['f IS MISSING', 'f IS EMPTY'],
        ['f EXISTS', 'f IS NOT EMPTY'],
    ];

    aliases.forEach(([alias, canonical]) => {
        const actual = parseAQLQuery(alias);
        expect(actual).toBeDefined();
        expect(actual).toEqual(parseAQLQuery(canonical));
        expect(astToString(actual as AQLQueryAST)).toEqual(canonical);
    });
});
