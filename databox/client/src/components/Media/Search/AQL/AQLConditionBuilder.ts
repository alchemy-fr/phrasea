import {AQLAndOrExpression, AQLCondition, AQLLiteral, AQLOperand, AQLQueryAST} from "./aqlTypes.ts";
import {isAQLField, resolveAQLValue, ScalarValue} from "./query.ts";

type Props = {
    field: string;
    values?: ScalarValue[];
    includeMissing?: boolean;
}

export class AQLConditionBuilder {
    private readonly field: string;
    private values: ScalarValue[] = [];
    public includeMissing: boolean = false;

    constructor({field, values, includeMissing}: Props) {
        this.field = field;
        this.values = values ?? [];
        this.includeMissing = includeMissing || false;
    }

    public addValue(value: ScalarValue) {
        this.values.push(value);

        return this;
    }

    public removeValue(value: ScalarValue) {
        this.values = this.values.filter(v => v !== value);

        return this;
    }

    public toggleValue(value: ScalarValue) {
        return this.hasValue(value) ? this.removeValue(value) : this.addValue(value);
    }

    public getValues(): ScalarValue[] {
        return this.values;
    }

    public hasValue(value: ScalarValue): boolean {
        return this.values.includes(value);
    }

    public toString(): string {
        const conditions: string[] = [];

        if (this.values.length > 0) {
            conditions.push(`${this.field} ${this.values.length > 1 ? 'IN (' : '= '}${this.values.map(v => {
                return typeof v === 'string' ? `"${v}"` : v;
            }).join(', ')}${this.values.length > 1 ? ')' : ''}`);
        }

        if (this.includeMissing) {
            conditions.push(`${this.field} IS MISSING`);
        }
        const output = conditions.join(' OR ');
        console.trace('toString', output);

        return output;
    }

    public static fromQuery(field: string, query: AQLQueryAST | undefined) {
        let values: ScalarValue[] | undefined = undefined;
        let includeMissing: boolean = false;

        function hasProp<T>(object: any, key: string): object is T {
            return typeof object === 'object' && Object.prototype.hasOwnProperty.call(object, key);
        }

        function resolveValue(value: AQLOperand): ScalarValue {
            return resolveAQLValue(value, true);
        }

        if (query) {
            let conditions: AQLCondition[];
            if (hasProp<AQLAndOrExpression>(query.expression, 'conditions')) {
                conditions = query.expression.conditions;
            } else if (hasProp<AQLCondition>(query.expression, 'leftOperand')) {
                conditions = [query.expression];
            } else {
                console.debug('expression', query.expression);
                throw new Error(`Unsupported expression`);
            }
            const condition = conditions[0];
            if (condition) {
                if (isAQLField(condition.leftOperand)) {
                    if (field !== condition.leftOperand.field) {
                        throw new Error('Field mismatch');
                    }
                } else {
                    throw new Error('Expected field in left operand');
                }

                const rightOperand = condition.rightOperand;
                if (rightOperand) {
                    if (Array.isArray(rightOperand)) {
                        values = rightOperand.map(resolveValue);
                    } else {
                        values = [resolveValue(rightOperand)];
                    }
                }
            }
        }

        return new AQLConditionBuilder({
            field,
            values,
            includeMissing,
        })
    }
}
