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
                        {
                            operator: 'AND',
                            conditions: [
                                {leftOperand: {field: 'field1'}, operator: '=', rightOperand: {literal: 'foo'}},
                                {leftOperand: {field: 'price'}, operator: '>', rightOperand: 42},
                            ],
                        }
                    ],
                },
            },
        },
        {
            query: '@createdAt != "foo"',
            result: {
                expression: {
                    operator: 'AND',
                    conditions: [
                        {leftOperand: {key: 'createdAt'}, operator: '!=', rightOperand: {literal: 'foo'}},
                    ],
                },
            },
        },
    ];

    const query = 'field1 = "foo" AND price > 42';
    const result = parseAQLQuery(query);


    dataSet.forEach(({query, result}) => {
        const actual = parseAQLQuery(query);
        console.log(util.inspect(actual, {showHidden: false, depth: null, colors: true}))
        expect(actual).toEqual(result);
    });
});
