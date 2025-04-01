import {AttributeDefinitionIndex} from "../../../AttributeEditor/types.ts";
import {AQLCondition, AQLLiteral, AQLOperator, aqlOperators, AQLQueryAST, RawType} from "./aqlTypes.ts";
import {hasProp} from "../../../../lib/utils.ts";
import {AttributeDefinition} from "../../../../types.ts";
import {isAQLCondition, isAQLField, valueToString} from "./query.ts";

export const typeMap: Record<string, RawType> = {
    boolean: RawType.Boolean,
    code: RawType.String,
    collection_path: RawType.String,
    color: RawType.String,
    date: RawType.Date,
    date_time: RawType.Date,
    entity: RawType.String,
    html: RawType.String,
    ip: RawType.String,
    keyword: RawType.String,
    number: RawType.Number,
    textarea: RawType.String,
    text: RawType.String,
}

export function validateQueryAST(query: AQLQueryAST, definitionsIndex: AttributeDefinitionIndex): void {
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

function validateConditionType(node: AQLCondition, definitionsIndex: AttributeDefinitionIndex): void {
    const op = node.operator as AQLOperator;
    const leftOperand = node.leftOperand;

    if (aqlOperators.includes(op)) {
        const attributeDefinition = validateField(leftOperand, definitionsIndex);
        if (attributeDefinition) {
            const type = attributeDefinition.fieldType;
            const rawType = typeMap[type];
            if (!rawType) {
                return;
            }

            if ([
                'CONTAINS',
                'MATCHES',
            ].includes(op) && rawType !== RawType.String) {
                throw new Error(`Field "${attributeDefinition.slug}" is not of type string`);
            }

            if ([
                '>',
                '>=',
                '<',
                '<=',
            ].includes(op) && ![
                RawType.Number,
                RawType.Date,
            ].includes(rawType)) {
                throw new Error(`Field "${attributeDefinition.slug}" is not of type number`);
            }

            if (!['MISSING', 'EXISTS'].includes(op)) {
                validateOfType(node.rightOperand, rawType!, definitionsIndex);
            }
        }
    }
}

function validateOfType(node: any, type: RawType, definitionsIndex: AttributeDefinitionIndex): void {
    if (typeof node === 'object') {
        if (isAQLField(node)) {
            const f = validateField(node, definitionsIndex);
            if (f && typeMap[f.fieldType] !== type) {
                throw new Error(`Field "${f.slug}" is not of type ${type}`);
            }

            return;
        }
    }

    if (Array.isArray(node)) {
        node.map(n => validateOfType(n, type, definitionsIndex));
    } else if (type === RawType.String && !hasProp<AQLLiteral>(node, 'literal')) {
        throw new Error(`Value ${valueToString(node)} is not of type string`);
    } else if (type === RawType.Number && typeof node !== 'number') {
        throw new Error(`Value ${valueToString(node)} is not of type number`);
    } else if (type === RawType.Boolean && typeof node !== 'boolean') {
        throw new Error(`Value ${valueToString(node)} is not of type boolean`);
    }
}

function validateField(node: any, definitionsIndex: AttributeDefinitionIndex): AttributeDefinition | undefined {
    if (isAQLField(node)) {
        const field = node.field;

        if (!definitionsIndex[field]) {
            throw new Error(`Field "${field}" does not exist`);
        }

        return definitionsIndex[field];
    }
}
