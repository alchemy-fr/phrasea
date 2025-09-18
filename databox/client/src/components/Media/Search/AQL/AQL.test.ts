import {parseAQLQuery} from './AQL.ts';
import {astToString} from './query.ts';
import {AQLQueryAST} from './aqlTypes.ts';

it('parse AQL', function () {
    const dataSet = [
        {
            query: '@tag = true',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: true,
                },
            },
        },
        {
            query: '@tag = false',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: false,
                },
            },
        },
        {
            query: '@tag = null',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: '=',
                    rightOperand: null,
                },
            },
        },
        {
            query: '@tag = (1 + 2 )',
            formattedQuery: '@tag = (1 + 2)',
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
            query: 'field1 = "foo" AND price > 42',
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
            query: '@createdAt != "foo"',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: '!=',
                    rightOperand: {literal: 'foo'},
                },
            },
        },
        {
            query: '@createdAt != "f\\"oo"',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: '!=',
                    rightOperand: {literal: 'f"oo'},
                },
            },
        },
        {
            query: '@createdAt != "f\\"oo\\""',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'},
                    operator: '!=',
                    rightOperand: {literal: 'f"oo"'},
                },
            },
        },
        {
            query: '@createdAt != "fo"o"',
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
            query: '@tag IN ( "c333940d-9e5c-4f3c-b16a-77f8daabca87","6ee44526-3e8e-4412-8a9b-44b82fdce6bc" )',
            formattedQuery:
                '@tag IN ("c333940d-9e5c-4f3c-b16a-77f8daabca87", "6ee44526-3e8e-4412-8a9b-44b82fdce6bc")',
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
            query: '@tag IN (3, 2, 1)',
            result: {
                expression: {
                    leftOperand: {field: '@tag'},
                    operator: 'IN',
                    rightOperand: [3, 2, 1],
                },
            },
        },
        {
            query: '@tag NOT IN (true)',
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
            query: '(f1 = "1" AND f2 != "2") AND f3 != "3"',
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
            query: '(f1 = "1" AND f2 != "2") OR f3 != "3"',
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
            query: 'f1 = "1" AND (f2 != "2" AND f3 != "3")',
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
            query: 'f1 = "1" AND (f2 != "2" OR f3 != "3")',
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
            query: 'f1 = "1" OR (f2 != "2" AND f3 != "3")',
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
