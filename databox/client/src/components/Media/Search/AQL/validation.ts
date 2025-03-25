import {AttributeDefinitionIndex} from "../../../AttributeEditor/types.ts";
import {AQLCondition, AQLField, AQLLiteral, AQLOperator, aqlOperators, AQLQueryAST} from "./aqlTypes.ts";
import {hasProp} from "../../../../lib/utils.ts";
import {AttributeDefinition} from "../../../../types.ts";
import {isAQLCondition, isAQLField, valueToString} from "./query.ts";

enum RawType {
    STRING = 'string',
    NUMBER = 'number',
    DATE = 'date',
    BOOLEAN = 'boolean',
}

const typeMap: Record<string, RawType> = {
    boolean: RawType.BOOLEAN,
    code: RawType.STRING,
    collection_path: RawType.STRING,
    color: RawType.STRING,
    date: RawType.DATE,
    date_time: RawType.DATE,
    entity: RawType.STRING,
    html: RawType.STRING,
    ip: RawType.STRING,
    keyword: RawType.STRING,
    number: RawType.NUMBER,
    textarea: RawType.STRING,
    text: RawType.STRING,
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
            ].includes(op) && rawType !== RawType.STRING) {
                throw new Error(`Field "${attributeDefinition.slug}" is not of type string`);
            }

            if ([
                '>',
                '>=',
                '<',
                '<=',
            ].includes(op) && rawType !== RawType.NUMBER) {
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
    } else if (type === RawType.STRING && !hasProp<AQLLiteral>(node, 'literal')) {
        throw new Error(`Value ${valueToString(node)} is not of type string`);
    } else if (type === RawType.NUMBER && typeof node !== 'number') {
        throw new Error(`Value ${valueToString(node)} is not of type number`);
    } else if (type === RawType.BOOLEAN && typeof node !== 'boolean') {
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
