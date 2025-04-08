export const aqlOperators = [
    '=',
    '!=',
    '>'
    , '<',
    '>='
    , '<=', 'IN', 'NOT_IN', 'MISSING', 'EXISTS',
    'CONTAINS',
    'NOT_CONTAINS',
    'MATCHES',
    'NOT_MATCHES',
    'STARTS_WITH',
    'NOT_STARTS_WITH',
    'BETWEEN'
    , 'BETWEEN'
    , 'NOT_BETWEEN',
] as const

export type AQLOperator = typeof aqlOperators[number];
export type AQLField = { field: string };
export type AQLFunctionCall = {
    type: 'function_call';
    function: string
    arguments: AQLValueOrExpression[];
};

export type AQLValueExpression = {
    type: 'value_expression';
    parentheses: boolean;
    operator: '+' | '-' | '*' | '/';
    leftOperand: AQLValueOrExpression;
    rightOperand: AQLValueOrExpression;
}

export type AQLParentheses = {
    type: 'parentheses';
    expression: AQLValueOrExpression;
}

export type AQLValueOrExpression = AQLParentheses | AQLValueExpression | AQLValue;

export type AQLLiteral = { literal: string };

export type AQLValue = AQLFunctionCall | AQLScalarValue;

export type AQLScalarValue = AQLLiteral | boolean | number | AQLField;

export type AQLOperand = AQLValueOrExpression;
export type RightOperand = AQLOperand | AQLOperand[] | undefined;
export type AQLCondition = {
    leftOperand: AQLOperand;
    rightOperand: RightOperand;
    operator: AQLOperator;
}
export type AQLExpression = AQLAndOrExpression | AQLCondition;

export type AQLAndOrExpression = {
    operator?: 'AND' | 'OR';
    conditions: AQLCondition[];
}

export type AQLQueryAST = {
    expression: AQLExpression;
}
export type ManyArgs = number | true | undefined;

export enum RawType {
    String = 'string',
    Number = 'number',
    Date = 'date',
    Boolean = 'boolean',
}
