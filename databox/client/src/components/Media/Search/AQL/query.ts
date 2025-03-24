import {
    AQLAndOrExpression,
    AQLCondition, AQLExpression,
    AQLField,
    AQLLiteral,
    AQLOperator,
    AQLQueryAST,
    AQLValue,
    RightOperand
} from "./aqlTypes.ts";
import {hasProp} from "../../../../lib/utils.ts";

export type AQLQuery = {
    id: string;
    query: string;
    disabled?: boolean;
    inversed?: boolean;
};

export type AQLQueries = AQLQuery[];

export function resolveValue(value: AQLValue): string {
    if (typeof value === 'object' && hasProp<AQLLiteral>(value, 'literal')) {
        return value.literal;
    }

    return value.toString();
}

export function astToString(ast: object | null | undefined): string {
    if (!ast || !hasProp<AQLQueryAST>(ast, 'expression')) {
        return '';
    }
    const expr = ast.expression;

    return expressionToString(expr);
}

function expressionToString(expression: AQLExpression, isSubExpression?: boolean): string {
    if (hasProp<AQLAndOrExpression>(expression, 'conditions')) {
        const r = expression.conditions.map(e => expressionToString(e, true)).join(` ${expression.operator} `);

        return isSubExpression ? `(${r})` : r;
    }

    return conditionToString(expression);
}

function conditionToString(condition: AQLCondition): string {
    const left = operandToString(condition.leftOperand);
    const right = operandToString(condition.rightOperand, condition.operator);

    return `${left} ${operatorToString(condition.operator)} ${right}`;
}

function operandToString(operand: RightOperand, operator?: AQLOperator): string {
    if (typeof operand === 'object') {
        if (operator && Array.isArray(operand)) {
            if (['IN', 'NOT_IN'].includes(operator)) {
                return `(${operand.map(o => operandToString(o)).join(', ')})`;
            } else if (['BETWEEN', 'NOT_BETWEEN'].includes(operator)) {
                return operand.map(o => operandToString(o)).join(' AND ');
            }
        } else {
            if (hasProp<AQLField>(operand, 'field')) {
                return operand.field;
            }
        }
    }

    return valueToString(operand as AQLValue);
}

function operatorToString(operator: AQLOperator): string {
    switch (operator) {
        case 'NOT_IN':
            return 'NOT IN';
        case 'MISSING':
            return 'IS MISSING';
        case 'NOT_BETWEEN':
            return 'NOT BETWEEN';
        default:
            return operator;
    }
}

export function valueToString(value: AQLValue): string {
    if (typeof value === 'object' && hasProp<AQLLiteral>(value, 'literal')) {
        return `"${value.literal.replace(/"/g, '\\"')}"`;
    }

    return value.toString();
}
