import {
    AQLAndOrExpression,
    AQLCondition,
    AQLExpression,
    AQLField,
    AQLFunctionCall,
    AQLLiteral,
    AQLOperand,
    AQLOperator,
    AQLParentheses,
    AQLQueryAST,
    AQLValue,
    AQLValueExpression,
    AQLValueOrExpression,
    RightOperand
} from "./aqlTypes.ts";
import {hasProp} from "../../../../lib/utils.ts";
import {AttributeDefinitionIndex} from "../../../AttributeEditor/types.ts";
import {AttributeDefinition} from "../../../../types.ts";

export type AQLQuery = {
    id: string;
    query: string;
    disabled?: boolean;
    inversed?: boolean;
};

export type AQLQueries = AQLQuery[];

export function astToString(ast: object | null | undefined): string {
    if (!ast || !hasProp<AQLQueryAST>(ast, 'expression')) {
        return '';
    }
    const expr = ast.expression;

    return expressionToString(expr);
}

function expressionToString(expression: AQLExpression, isSubExpression?: boolean): string {
    if (hasProp<AQLAndOrExpression>(expression, 'conditions')) {
        const r = expression.conditions
            .filter(c => {
                if (typeof c.leftOperand === 'object' && isAQLField(c.leftOperand)) {
                    return !!c.leftOperand.field;
                }

                return true;
            })
            .map(e => expressionToString(e, true)).join(` ${expression.operator} `);

        return isSubExpression ? `(${r})` : r;
    }

    return conditionToString(expression);
}


function functionCallToString(expression: AQLFunctionCall): string {
    return `${expression.function}(${expression.arguments.map(a => operandToString(a)).join(', ')})`;
}


export function valueExpressionToString(expression: AQLValueExpression): string {
    const left = operandToString(expression.leftOperand);
    const right = operandToString(expression.rightOperand);

    return `${left} ${expression.operator} ${right}`;
}

function conditionToString(condition: AQLCondition): string {
    const left = operandToString(condition.leftOperand);
    const right = operandToString(condition.rightOperand, condition.operator);

    return `${left} ${operatorToString(condition.operator)} ${right}`.trim();
}

function operandToString(operand: RightOperand, operator?: AQLOperator): string {
    if (undefined === operand) {
        return '';
    }

    if (typeof operand === 'object') {
        if (operator && Array.isArray(operand)) {
            if ([AQLOperator.IN, AQLOperator.NOT_IN].includes(operator)) {
                return `(${operand.map(o => operandToString(o)).join(', ')})`;
            } else if ([AQLOperator.BETWEEN, AQLOperator.NOT_BETWEEN].includes(operator)) {
                return operand.map(o => operandToString(o)).join(' AND ');
            } else if ([AQLOperator.WITHIN_CIRCLE, AQLOperator.WITHIN_RECTANGLE].includes(operator)) {
                return `(${operand.map(o => operandToString(o)).join(', ')})`;
            }
        } else {
            if (isAQLField(operand as AQLOperand)) {
                return (operand as AQLField).field;
            }
        }
    }

    return valueToString(operand as AQLValue);
}

function operatorToString(operator: AQLOperator): string {
    const map: Record<AQLOperator, string> = {
        '=': '=',
        '!=': '!=',
        '>': '>',
        '<': '<',
        '>=': '>=',
        '<=': '<=',
        'IN': 'IN',
        'NOT_IN': 'NOT IN',
        'MISSING': 'IS MISSING',
        'EXISTS': 'EXISTS',
        'CONTAINS': 'CONTAINS',
        'NOT_CONTAINS': 'DOES NOT CONTAIN',
        'MATCHES': 'MATCHES',
        'NOT_MATCHES': 'DOES NOT MATCH',
        'STARTS_WITH': 'STARTS WITH',
        'NOT_STARTS_WITH': 'DOES NOT START WITH',
        'BETWEEN': 'BETWEEN',
        'NOT_BETWEEN': 'NOT BETWEEN',
        WITHIN_CIRCLE: 'WITHIN CIRCLE',
        WITHIN_RECTANGLE: 'WITHIN RECTANGLE',
    };

    return map[operator] || operator;
}

export function valueToString(value: AQLValueOrExpression): string {
    if (typeof value === 'object') {
        if (hasProp<AQLLiteral>(value, 'literal')) {
            return `"${value.literal.replace(/"/g, '\\"')}"`;
        } else if (isAQLParentheses(value)) {
            return `(${valueToString(value.expression)})`;
        } else if (isAQLValueExpression(value)) {
            return valueExpressionToString(value);
        } else if (isAQLFunctionCall(value)) {
            return functionCallToString(value);
        } else if (isAQLField(value)) {
            return value.field;
        }
    }

    return value.toString();
}

export function isAQLCondition(expression: AQLExpression): expression is AQLCondition {
    return hasProp<AQLCondition>(expression, 'leftOperand');
}

export function isAQLField(operand: AQLOperand): operand is AQLField {
    return hasProp<AQLField>(operand, 'field');
}

export function isAQLValueExpression(operand: AQLOperand): operand is AQLValueExpression {
    return hasProp<AQLValueExpression>(operand, 'type') && operand.type === 'value_expression';
}

export function isAQLFunctionCall(operand: AQLOperand): operand is AQLFunctionCall {
    return hasProp<AQLFunctionCall>(operand, 'type') && operand.type === 'function_call';
}

export function isAQLParentheses(operand: AQLOperand): operand is AQLParentheses {
    return hasProp<AQLParentheses>(operand, 'type') && operand.type === 'parentheses';
}

export function resolveAQLValue(value: AQLOperand, throwExceptionOnField = false): ScalarValue {
    if (hasProp<AQLLiteral>(value, 'literal')) {
        return value.literal;
    } else if (isAQLField(value)) {
        if (throwExceptionOnField) {
            throw new Error('Unsupported field operand');
        } else {
            return null;
        }
    } else if (isAQLParentheses(value)) {
        throw new Error('Cannot resolve value from parentheses');
    } else if (isAQLValueExpression(value)) {
        throw new Error('Cannot resolve value from expression');
    } else if (isAQLFunctionCall(value)) {
        throw new Error('Cannot resolve value from function call');
    }

    return value;
}

export type ScalarValue = string | boolean | number | null;

export function getFieldDefinition(node: any, definitionsIndex: AttributeDefinitionIndex): AttributeDefinition | undefined {
    if (isAQLField(node)) {
        const field = node.field;

        return definitionsIndex[field];
    }
}
