import {AttributeDefinitionIndex} from '../../../AttributeEditor/types.ts';
import {
    AQLCondition,
    AQLLiteral,
    AQLOperator,
    AQLQueryAST,
    RawType,
} from './aqlTypes.ts';
import {hasProp} from '../../../../lib/utils.ts';
import {AttributeDefinition} from '../../../../types.ts';
import {isAQLCondition, isAQLField, valueToString} from './query.ts';
import {AttributeType} from '../../../../api/attributes.ts';

export const typeMap: Record<AttributeType, RawType> = {
    [AttributeType.Boolean]: RawType.Boolean,
    [AttributeType.Code]: RawType.String,
    [AttributeType.CollectionPath]: RawType.String,
    [AttributeType.Color]: RawType.String,
    [AttributeType.DateTime]: RawType.DateTime,
    [AttributeType.Date]: RawType.Date,
    [AttributeType.Entity]: RawType.String,
    [AttributeType.GeoPoint]: RawType.GeoPoint,
    [AttributeType.Html]: RawType.String,
    [AttributeType.Id]: RawType.Id,
    [AttributeType.Ip]: RawType.String,
    [AttributeType.Json]: RawType.String,
    [AttributeType.Keyword]: RawType.Keyword,
    [AttributeType.Number]: RawType.Number,
    [AttributeType.Privacy]: RawType.Number,
    [AttributeType.Rendition]: RawType.String,
    [AttributeType.Tag]: RawType.Id,
    [AttributeType.Text]: RawType.String,
    [AttributeType.Textarea]: RawType.String,
    [AttributeType.User]: RawType.Id,
    [AttributeType.WebVtt]: RawType.String,
    [AttributeType.Workspace]: RawType.String,
};

export function validateQueryAST(
    query: AQLQueryAST,
    definitionsIndex: AttributeDefinitionIndex
): void {
    function visitNode(node: any): void {
        if (typeof node === 'object') {
            if (isAQLCondition(node)) {
                validateConditionType(node, definitionsIndex);
            }

            if (validateField(node, definitionsIndex)) {
                return;
            }

            Object.keys(node).forEach(k => {
                if (Array.isArray(node[k])) {
                    node[k].map(visitNode);
                } else {
                    visitNode(node[k]);
                }
            });
        }
    }

    visitNode(query.expression);
}

function validateConditionType(
    node: AQLCondition,
    definitionsIndex: AttributeDefinitionIndex
): void {
    const op = node.operator as AQLOperator;
    const leftOperand = node.leftOperand;

    if (Object.values(AQLOperator).includes(op)) {
        const attributeDefinition = validateField(
            leftOperand,
            definitionsIndex
        );
        if (attributeDefinition) {
            const type = attributeDefinition.fieldType;
            const rawType = typeMap[type];
            if (!rawType) {
                return;
            }

            const throwTypeError = (type: string) => {
                throw new Error(
                    `Field "${attributeDefinition.name}" is of type ${type} and cannot be used with "${op}" operator.`
                );
            };
            const throwNotOfTypeError = (type: string) => {
                throw new Error(
                    `Field "${attributeDefinition.name}" is not of type ${type}`
                );
            };

            if (
                [
                    AQLOperator.CONTAINS,
                    AQLOperator.NOT_CONTAINS,
                    AQLOperator.STARTS_WITH,
                    AQLOperator.NOT_STARTS_WITH,
                ].includes(op) &&
                ![RawType.Keyword, RawType.String].includes(rawType)
            ) {
                if (rawType === RawType.Id) {
                    throwTypeError('ID');
                }

                throwNotOfTypeError('string');
            }

            if (
                [AQLOperator.MATCHES, AQLOperator.NOT_MATCHES].includes(op) &&
                ![RawType.String].includes(rawType)
            ) {
                if (rawType === RawType.Id) {
                    throwTypeError('ID');
                }
                if (rawType === RawType.Keyword) {
                    throwTypeError('keyword');
                }

                throwNotOfTypeError('string');
            }

            if (
                [
                    AQLOperator.WITHIN_CIRCLE,
                    AQLOperator.WITHIN_RECTANGLE,
                ].includes(op) &&
                rawType !== RawType.GeoPoint
            ) {
                throwNotOfTypeError('Geo Point');
            }

            if (
                [
                    AQLOperator.GT,
                    AQLOperator.GTE,
                    AQLOperator.LT,
                    AQLOperator.LTE,
                ].includes(op) &&
                ![RawType.Number, RawType.Date, RawType.DateTime].includes(
                    rawType
                )
            ) {
                throwNotOfTypeError('number');
            }

            if (![AQLOperator.MISSING, AQLOperator.EXISTS].includes(op)) {
                validateOfType(node.rightOperand, rawType!, definitionsIndex);
            }
        }
    }
}

function validateOfType(
    node: any,
    type: RawType,
    definitionsIndex: AttributeDefinitionIndex
): void {
    if (typeof node === 'object') {
        if (isAQLField(node)) {
            const f = validateField(node, definitionsIndex);
            if (f && typeMap[f.fieldType] !== type) {
                throw new Error(`Field "${f.name}" is not of type ${type}`);
            }

            return;
        }
    }

    if (Array.isArray(node)) {
        node.map(n => validateOfType(n, type, definitionsIndex));
    } else if (
        type === RawType.String &&
        !hasProp<AQLLiteral>(node, 'literal')
    ) {
        throw new Error(`Value ${valueToString(node)} is not of type string`);
    } else if (type === RawType.Number && typeof node !== 'number') {
        throw new Error(`Value ${valueToString(node)} is not of type number`);
    } else if (type === RawType.Boolean && typeof node !== 'boolean') {
        throw new Error(`Value ${valueToString(node)} is not of type boolean`);
    }
}

function validateField(
    node: any,
    definitionsIndex: AttributeDefinitionIndex
): AttributeDefinition | undefined {
    if (isAQLField(node)) {
        const field = node.field;

        if (!definitionsIndex[field]) {
            throw new Error(`Field "${field}" does not exist`);
        }

        return definitionsIndex[field];
    }
}
