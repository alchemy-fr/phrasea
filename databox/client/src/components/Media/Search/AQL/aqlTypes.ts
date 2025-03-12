
export type AQLOperator = '=' | '!=' | '>' | '<' | '>=' | '<=' | 'IN' | 'NOT IN' | 'IS MISSING' | 'IS NOT MISSING';
export type AQLField = {field: string};
export type AQLLiteral = {literal: string};
export type AQLValue = AQLLiteral | boolean | number;
export type AQLOperand = AQLField | AQLValue;
export type AQLCondition = {
    leftOperand: AQLOperand;
    rightOperand: AQLOperand | [AQLOperand, AQLOperand] | AQLOperand[];
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
