import {
    AQLCondition,
    AQLExpression,
    AQLLogical,
    AQLOperator,
    AQLQueryAST,
    AQLValueExpr,
    isArithmetic,
    isCondition,
    isEntity,
    isField,
    isFunctionCall,
    isLiteral,
    isLogical,
    isParentheses,
    ScalarValue,
} from './types';

export const operatorLabels: Record<AQLOperator, string> = {
    [AQLOperator.EQ]: '=',
    [AQLOperator.NEQ]: '!=',
    [AQLOperator.GT]: '>',
    [AQLOperator.LT]: '<',
    [AQLOperator.GTE]: '>=',
    [AQLOperator.LTE]: '<=',
    [AQLOperator.IN]: 'IN',
    [AQLOperator.NOT_IN]: 'NOT IN',
    [AQLOperator.MISSING]: 'IS MISSING',
    [AQLOperator.EXISTS]: 'EXISTS',
    [AQLOperator.CONTAINS]: 'CONTAINS',
    [AQLOperator.NOT_CONTAINS]: 'DOES NOT CONTAIN',
    [AQLOperator.MATCHES]: 'MATCHES',
    [AQLOperator.NOT_MATCHES]: 'DOES NOT MATCH',
    [AQLOperator.STARTS_WITH]: 'STARTS WITH',
    [AQLOperator.NOT_STARTS_WITH]: 'DOES NOT START WITH',
    [AQLOperator.BETWEEN]: 'BETWEEN',
    [AQLOperator.NOT_BETWEEN]: 'NOT BETWEEN',
    [AQLOperator.WITHIN_CIRCLE]: 'WITHIN CIRCLE',
    [AQLOperator.WITHIN_RECTANGLE]: 'WITHIN RECTANGLE',
};

export function writeEntity(id: string, label: string): string {
    return `@<${id}:${label}>`;
}

export function quote(value: string): string {
    return `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
}

export function valueToString(value: AQLValueExpr | undefined): string {
    if (value === undefined) {
        return '';
    }
    if (value === null) {
        return 'null';
    }
    if (typeof value === 'boolean' || typeof value === 'number') {
        return String(value);
    }
    if (isLiteral(value)) {
        return quote(value.literal);
    }
    if (isEntity(value)) {
        return writeEntity(value.id, value.label);
    }
    if (isField(value)) {
        return value.field;
    }
    if (isParentheses(value)) {
        return `(${valueToString(value.expression)})`;
    }
    if (isArithmetic(value)) {
        return `${valueToString(value.leftOperand)} ${value.operator} ${valueToString(value.rightOperand)}`;
    }
    if (isFunctionCall(value)) {
        return `${value.function}(${value.arguments.map(a => valueToString(a)).join(', ')})`;
    }

    return String(value);
}

function rightOperandToString(condition: AQLCondition): string {
    const {operator, rightOperand} = condition;
    if (rightOperand === undefined) {
        return '';
    }
    if (Array.isArray(rightOperand)) {
        const parts = rightOperand.map(v => valueToString(v));
        if (
            operator === AQLOperator.BETWEEN ||
            operator === AQLOperator.NOT_BETWEEN
        ) {
            return parts.join(' AND ');
        }

        return `(${parts.join(', ')})`;
    }

    return valueToString(rightOperand);
}

export function conditionToString(condition: AQLCondition): string {
    return `${valueToString(condition.leftOperand)} ${operatorLabels[condition.operator] ?? condition.operator} ${rightOperandToString(condition)}`.trim();
}

export function expressionToString(
    expression: AQLExpression,
    nested = false
): string {
    if (isLogical(expression)) {
        if (expression.operator === AQLLogical.NOT) {
            return `NOT ${expressionToString(expression.conditions[0], true)}`;
        }
        const parts = expression.conditions
            .filter(
                c =>
                    !(
                        isCondition(c) &&
                        isField(c.leftOperand) &&
                        !c.leftOperand.field
                    )
            )
            .map(c => expressionToString(c, true));
        const joined = parts.join(` ${expression.operator} `);

        return nested && parts.length > 1 ? `(${joined})` : joined;
    }

    return conditionToString(expression);
}

export function astToString(ast: AQLQueryAST | undefined): string {
    return ast ? expressionToString(ast.expression) : '';
}

/**
 * Resolves a scalar value from a value expression. Throws for non-scalar
 * expressions (functions, arithmetic...).
 */
export function resolveScalar(
    value: AQLValueExpr,
    throwOnField = false
): ScalarValue {
    if (value === null || typeof value !== 'object') {
        return value;
    }
    if (isLiteral(value)) {
        return value.literal;
    }
    if (isEntity(value)) {
        return writeEntity(value.id, value.label);
    }
    if (isField(value)) {
        if (throwOnField) {
            throw new Error('Unsupported field operand');
        }

        return null;
    }
    throw new Error('Cannot resolve a scalar value from a complex expression');
}

/**
 * Builds simple `field IN (...)` / `field = x` / `field IS MISSING` conditions,
 * used by facets to toggle values.
 */
export class ConditionBuilder {
    private values: ScalarValue[];

    constructor(
        public readonly field: string,
        values: ScalarValue[] = [],
        public includeMissing = false
    ) {
        this.values = values;
    }

    static fromQuery(
        field: string,
        ast: AQLQueryAST | undefined
    ): ConditionBuilder {
        const builder = new ConditionBuilder(field);
        if (!ast) {
            return builder;
        }
        const conditions: AQLCondition[] = isLogical(ast.expression)
            ? (ast.expression.conditions.filter(isCondition) as AQLCondition[])
            : [ast.expression as AQLCondition];

        for (const condition of conditions) {
            if (
                !isField(condition.leftOperand) ||
                condition.leftOperand.field !== field
            ) {
                continue;
            }
            if (condition.operator === AQLOperator.MISSING) {
                builder.includeMissing = true;
                continue;
            }
            const right = condition.rightOperand;
            if (right === undefined) {
                continue;
            }
            const list = Array.isArray(right) ? right : [right];
            for (const v of list) {
                try {
                    builder.values.push(resolveScalar(v, true));
                } catch {
                    // ignore non-scalar values
                }
            }
        }

        return builder;
    }

    hasValue(value: ScalarValue): boolean {
        return this.values.includes(value);
    }

    getValues(): ScalarValue[] {
        return this.values;
    }

    toggleValue(value: ScalarValue): this {
        this.values = this.hasValue(value)
            ? this.values.filter(v => v !== value)
            : [...this.values, value];

        return this;
    }

    setValues(values: ScalarValue[]): this {
        this.values = values;

        return this;
    }

    toString(): string {
        const parts: string[] = [];
        if (this.values.length > 0) {
            const serialized = this.values.map(v =>
                typeof v === 'string' ? quote(v) : String(v)
            );
            parts.push(
                this.values.length > 1
                    ? `${this.field} IN (${serialized.join(', ')})`
                    : `${this.field} = ${serialized[0]}`
            );
        }
        if (this.includeMissing) {
            parts.push(`${this.field} IS MISSING`);
        }

        return parts.join(' OR ');
    }
}
