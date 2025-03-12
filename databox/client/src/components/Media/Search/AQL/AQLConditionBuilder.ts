import {AQLAndOrExpression, AQLCondition, AQLField, AQLLiteral, AQLOperand, AQLQueryAST} from "./aqlTypes.ts";

type Props = {
    field: string;
    values?: ScalarValue[];
    includeMissing?: boolean;
}

type ScalarValue = string | boolean | number;

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
        this.values = this.values.filter(v => v ! === value);

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
            if (hasProp<AQLLiteral>(value, 'literal')) {
                return value.literal;
            }
            if (hasProp<AQLField>(value, 'field')) {
                throw new Error('Unsupported field operant');
            }

            return value;
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
                if (hasProp<AQLField>(condition.leftOperand, 'field')) {
                    if (field !== condition.leftOperand.field) {
                        throw new Error('Field mismatch');
                    }
                } else {
                    throw new Error('Expected field in left operand');
                }

                const rightOperand = condition.rightOperand;
                if (Array.isArray(rightOperand)) {
                    values = rightOperand.map(resolveValue);
                } else {
                    values = [resolveValue(rightOperand)];
                }

                // TODO handle IS MISSING
            }
        }

        return new AQLConditionBuilder({
            field,
            values,
            includeMissing,
        })
    }
}
