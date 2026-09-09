/**
 * AQL (Asset Query Language) abstract syntax tree.
 */

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

export enum AQLLogical {
    AND = 'AND',
    OR = 'OR',
    NOT = 'NOT',
}

export type AQLField = {field: string};
export type AQLLiteral = {literal: string};
export type AQLEntity = {type: 'entity'; id: string; label: string};

export type AQLFunctionCall = {
    type: 'function_call';
    function: string;
    arguments: AQLValueExpr[];
};

export type AQLArithmetic = {
    type: 'value_expression';
    operator: '+' | '-' | '*' | '/';
    leftOperand: AQLValueExpr;
    rightOperand: AQLValueExpr;
};

export type AQLParentheses = {
    type: 'parentheses';
    expression: AQLValueExpr;
};

export type AQLScalar =
    | AQLLiteral
    | AQLEntity
    | AQLField
    | boolean
    | number
    | null;
export type AQLValue = AQLScalar | AQLFunctionCall;
export type AQLValueExpr = AQLValue | AQLArithmetic | AQLParentheses;

export type AQLCondition = {
    leftOperand: AQLValueExpr;
    operator: AQLOperator;
    rightOperand?: AQLValueExpr | AQLValueExpr[];
};

export type AQLLogicalExpression = {
    operator: AQLLogical;
    conditions: AQLExpression[];
};

export type AQLExpression = AQLCondition | AQLLogicalExpression;

export type AQLQueryAST = {
    expression: AQLExpression;
};

export type ScalarValue = string | number | boolean | null;

// Type guards -----------------------------------------------------------------

function hasProp<T extends object>(v: unknown, prop: keyof T): v is T {
    return typeof v === 'object' && v !== null && prop in v;
}

export const isField = (v: unknown): v is AQLField =>
    hasProp<AQLField>(v, 'field');
export const isLiteral = (v: unknown): v is AQLLiteral =>
    hasProp<AQLLiteral>(v, 'literal');
export const isEntity = (v: unknown): v is AQLEntity =>
    hasProp<AQLEntity>(v, 'type') && (v as AQLEntity).type === 'entity';
export const isFunctionCall = (v: unknown): v is AQLFunctionCall =>
    hasProp<AQLFunctionCall>(v, 'type') &&
    (v as AQLFunctionCall).type === 'function_call';
export const isArithmetic = (v: unknown): v is AQLArithmetic =>
    hasProp<AQLArithmetic>(v, 'type') &&
    (v as AQLArithmetic).type === 'value_expression';
export const isParentheses = (v: unknown): v is AQLParentheses =>
    hasProp<AQLParentheses>(v, 'type') &&
    (v as AQLParentheses).type === 'parentheses';
export const isCondition = (v: unknown): v is AQLCondition =>
    hasProp<AQLCondition>(v, 'leftOperand');
export const isLogical = (v: unknown): v is AQLLogicalExpression =>
    hasProp<AQLLogicalExpression>(v, 'conditions');

/** Raw Elasticsearch-side type of an attribute, used for validation */
export enum RawType {
    String = 'string',
    Id = 'id',
    Keyword = 'keyword',
    Number = 'number',
    Date = 'date',
    DateTime = 'date_time',
    Boolean = 'boolean',
    GeoPoint = 'geo_point',
}
