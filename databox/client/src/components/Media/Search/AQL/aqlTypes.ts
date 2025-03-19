
export type AQLOperator = '=' | '!=' | '>' | '<' | '>=' | '<=' | 'IN' | 'NOT_IN' | 'MISSING' | 'EXISTS'
    | 'BETWEEN'
    | 'NOT_BETWEEN'
    ;
export type AQLField = {field: string};
export type AQLLiteral = {literal: string};
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
