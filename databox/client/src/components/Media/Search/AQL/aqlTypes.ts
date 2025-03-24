export const aqlOperators = [
    '=',
    '!=',
    '>'
    , '<',
    '>='
    , '<=', 'IN', 'NOT_IN', 'MISSING', 'EXISTS',
    'MATCHES',
    'NOT_MATCHES',
    'NOT_CONTAINS',
    'BETWEEN'
    , 'BETWEEN'
    , 'NOT_BETWEEN',
] as const

export type AQLOperator = typeof aqlOperators[number];
export type AQLField = { field: string };
export type AQLLiteral = { literal: string };
export type AQLValue = AQLLiteral | boolean | number;
export type AQLOperand = AQLField | AQLValue;
export type RightOperand = AQLOperand | AQLOperand[];
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
