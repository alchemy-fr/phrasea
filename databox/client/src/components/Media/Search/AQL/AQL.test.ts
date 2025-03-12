import {parseAQLQuery} from "./AQL.ts";
import util from "util";

it('parse AQL', function () {
    const dataSet = [
        {
            query: 'field1 = "foo" AND price > 42',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {leftOperand: {field: 'field1'}, operator: '=', rightOperand: {literal: 'foo'}},
                        {leftOperand: {field: 'price'}, operator: '>', rightOperand: 42},
                    ],
                },
            },
        },
        {
            query: '@createdAt != "foo"',
            result: {
                expression: {
                    leftOperand: {field: '@createdAt'}, operator: '!=', rightOperand: {literal: 'foo'}
                },
            },
        },
        {
            query: '@createdAt BETWEEN 1 AND 2',
            result: {
                expression:
                    {leftOperand: {field: '@createdAt'}, operator: 'BETWEEN', rightOperand: [1, 2]},
            },
        },
        {
            query: '@createdAt NOT  BETWEEN 1 AND 2',
            result: {
                expression:
                    {leftOperand: {field: '@createdAt'}, operator: 'NOT_BETWEEN', rightOperand: [1, 2]},
            },
        },
        {
            query: '@tag IN ( "c333940d-9e5c-4f3c-b16a-77f8daabca87","6ee44526-3e8e-4412-8a9b-44b82fdce6bc" )',
            result: {
                expression:
                    {leftOperand: {field: '@tag'}, operator: 'IN', rightOperand: [{literal: "c333940d-9e5c-4f3c-b16a-77f8daabca87"}, {literal: "6ee44526-3e8e-4412-8a9b-44b82fdce6bc"}]},
            },
        },
        {
            query: '@tag IN (3, 2, 1)',
            result: {
                expression:
                    {leftOperand: {field: '@tag'}, operator: 'IN', rightOperand: [3, 2, 1]},
            },
        },
        {
            query: '@tag IN (true)',
            result: {
                expression:
                    {leftOperand: {field: '@tag'}, operator: 'IN', rightOperand: [true]},
            },
        },
        {
            query: '@tag NOT IN (true)',
            result: {
                expression:
                    {leftOperand: {field: '@tag'}, operator: 'NOT_IN', rightOperand: [true]},
            },
        },
    ];

    dataSet.forEach(({query, result}) => {
        const actual = parseAQLQuery(query);
        expect(actual).toEqual(result);
    });
});
