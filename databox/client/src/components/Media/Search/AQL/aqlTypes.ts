
export enum AQLOperator {
    EQ = '=',
    NEQ = '!=',
    GT = '>',
    LT = '<',
    GTE = '>=',
    LTE = '<=',
    IN = 'IN',
    NOT_IN = 'NOT_IN',
    MISSING = 'MISSING',
    EXISTS = 'EXISTS',
    CONTAINS = 'CONTAINS',
    NOT_CONTAINS = 'NOT_CONTAINS',
    MATCHES = 'MATCHES',
    NOT_MATCHES = 'NOT_MATCHES',
    STARTS_WITH = 'STARTS_WITH',
    NOT_STARTS_WITH = 'NOT_STARTS_WITH',
    BETWEEN = 'BETWEEN',
    NOT_BETWEEN = 'NOT_BETWEEN',
    WITHIN_CIRCLE = 'WITHIN_CIRCLE',
    WITHIN_RECTANGLE = 'WITHIN_RECTANGLE',
}

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

export enum AQLAndOrOperator {
    AND = 'AND',
    OR = 'OR',
}

export type AQLAndOrExpression = {
    operator?: AQLAndOrOperator;
    conditions: AQLCondition[];
}

export type AQLQueryAST = {
    expression: AQLExpression;
}
export type ManyArgs = number | true | undefined;
export type ArgNames = string[] | undefined;

export enum RawType {
    String = 'string',
    Number = 'number',
    Date = 'date',
    Boolean = 'boolean',
    GeoPoint = 'geo_point',
}
