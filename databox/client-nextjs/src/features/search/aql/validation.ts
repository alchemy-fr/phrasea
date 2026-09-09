import {AttributeDefinitionOrBuiltIn, AttributeType} from '@/types/api';
import {
    AQLCondition,
    AQLOperator,
    AQLQueryAST,
    AQLValueExpr,
    isCondition,
    isField,
    isLiteral,
    isLogical,
    RawType,
} from './types';
import {valueToString} from './serializer';

export type DefinitionIndex = Record<string, AttributeDefinitionOrBuiltIn>;

export const rawTypeMap: Record<AttributeType, RawType> = {
    [AttributeType.Boolean]: RawType.Boolean,
    [AttributeType.Code]: RawType.String,
    [AttributeType.CollectionPath]: RawType.String,
    [AttributeType.Story]: RawType.String,
    [AttributeType.Color]: RawType.String,
    [AttributeType.DateTime]: RawType.DateTime,
    [AttributeType.Date]: RawType.Date,
    [AttributeType.Duration]: RawType.Number,
    [AttributeType.Entity]: RawType.String,
    [AttributeType.GeoPoint]: RawType.GeoPoint,
    [AttributeType.Html]: RawType.String,
    [AttributeType.Id]: RawType.Id,
    [AttributeType.Ip]: RawType.String,
    [AttributeType.Json]: RawType.String,
    [AttributeType.Keyword]: RawType.Keyword,
    [AttributeType.Number]: RawType.Number,
    [AttributeType.Privacy]: RawType.Number,
    [AttributeType.AssetStatus]: RawType.Number,
    [AttributeType.Rendition]: RawType.String,
    [AttributeType.FileSize]: RawType.Number,
    [AttributeType.FileType]: RawType.Keyword,
    [AttributeType.Tag]: RawType.Id,
    [AttributeType.Text]: RawType.String,
    [AttributeType.Textarea]: RawType.String,
    [AttributeType.User]: RawType.Id,
    [AttributeType.WebVtt]: RawType.String,
    [AttributeType.Workspace]: RawType.String,
};

export class AQLValidationError extends Error {
    name = 'AQLValidationError';
}

/** Operators allowed for each raw type */
export function getOperatorsForType(
    type: AttributeType | undefined
): AQLOperator[] {
    const raw = type ? rawTypeMap[type] : undefined;
    const base = [
        AQLOperator.EQ,
        AQLOperator.NEQ,
        AQLOperator.IN,
        AQLOperator.NOT_IN,
        AQLOperator.EXISTS,
        AQLOperator.MISSING,
    ];
    switch (raw) {
        case RawType.String:
            return [
                ...base,
                AQLOperator.CONTAINS,
                AQLOperator.NOT_CONTAINS,
                AQLOperator.MATCHES,
                AQLOperator.NOT_MATCHES,
                AQLOperator.STARTS_WITH,
                AQLOperator.NOT_STARTS_WITH,
            ];
        case RawType.Keyword:
            return [
                ...base,
                AQLOperator.CONTAINS,
                AQLOperator.NOT_CONTAINS,
                AQLOperator.STARTS_WITH,
                AQLOperator.NOT_STARTS_WITH,
            ];
        case RawType.Number:
        case RawType.Date:
        case RawType.DateTime:
            return [
                ...base,
                AQLOperator.GT,
                AQLOperator.GTE,
                AQLOperator.LT,
                AQLOperator.LTE,
                AQLOperator.BETWEEN,
                AQLOperator.NOT_BETWEEN,
            ];
        case RawType.GeoPoint:
            return [
                AQLOperator.WITHIN_CIRCLE,
                AQLOperator.WITHIN_RECTANGLE,
                AQLOperator.EXISTS,
                AQLOperator.MISSING,
            ];
        case RawType.Boolean:
            return [
                AQLOperator.EQ,
                AQLOperator.NEQ,
                AQLOperator.EXISTS,
                AQLOperator.MISSING,
            ];
        default:
            return Object.values(AQLOperator);
    }
}

/** Number of right operands for an operator: a number, `true` for variadic, 0 for none */
export function getOperatorArity(operator: AQLOperator): number | true {
    switch (operator) {
        case AQLOperator.IN:
        case AQLOperator.NOT_IN:
            return true;
        case AQLOperator.BETWEEN:
        case AQLOperator.NOT_BETWEEN:
            return 2;
        case AQLOperator.WITHIN_CIRCLE:
            return 3;
        case AQLOperator.WITHIN_RECTANGLE:
            return 4;
        case AQLOperator.EXISTS:
        case AQLOperator.MISSING:
            return 0;
        default:
            return 1;
    }
}

export function getOperatorArgNames(
    operator: AQLOperator
): string[] | undefined {
    switch (operator) {
        case AQLOperator.WITHIN_CIRCLE:
            return ['lat', 'lng', 'radius'];
        case AQLOperator.WITHIN_RECTANGLE:
            return [
                'topLeftLat',
                'topLeftLng',
                'bottomRightLat',
                'bottomRightLng',
            ];
        case AQLOperator.BETWEEN:
        case AQLOperator.NOT_BETWEEN:
            return ['from', 'to'];
        default:
            return undefined;
    }
}

function resolveField(
    node: unknown,
    index: DefinitionIndex
): AttributeDefinitionOrBuiltIn | undefined {
    if (isField(node)) {
        const def = index[node.field];
        if (!def) {
            throw new AQLValidationError(
                `Field "${node.field}" does not exist`
            );
        }

        return def;
    }

    return undefined;
}

function validateValueType(
    node: AQLValueExpr | AQLValueExpr[] | undefined,
    type: RawType,
    index: DefinitionIndex
): void {
    if (node === undefined) {
        return;
    }
    if (Array.isArray(node)) {
        node.forEach(n => validateValueType(n, type, index));

        return;
    }
    if (typeof node === 'object' && node !== null) {
        if (isField(node)) {
            const def = resolveField(node, index)!;
            if (rawTypeMap[def.type] !== type) {
                throw new AQLValidationError(
                    `Field "${def.name}" is not of type ${type}`
                );
            }
        }
        // functions / arithmetic / entities are accepted as-is

        return;
    }
    if (type === RawType.String && !isLiteral(node)) {
        throw new AQLValidationError(
            `Value ${valueToString(node)} is not of type string`
        );
    }
    if (type === RawType.Number && typeof node !== 'number') {
        throw new AQLValidationError(
            `Value ${valueToString(node)} is not of type number`
        );
    }
    if (
        type === RawType.Boolean &&
        typeof node !== 'boolean' &&
        node !== null
    ) {
        throw new AQLValidationError(
            `Value ${valueToString(node)} is not of type boolean`
        );
    }
}

function validateCondition(node: AQLCondition, index: DefinitionIndex): void {
    const def = resolveField(node.leftOperand, index);
    if (!def) {
        return;
    }
    const raw = rawTypeMap[def.type];
    if (!raw) {
        return;
    }
    const op = node.operator;
    if (!getOperatorsForType(def.type).includes(op)) {
        throw new AQLValidationError(
            `Field "${def.name}" is of type ${raw} and cannot be used with "${op}" operator.`
        );
    }
    if (op !== AQLOperator.MISSING && op !== AQLOperator.EXISTS) {
        validateValueType(node.rightOperand, raw, index);
    }
}

export function validateAST(ast: AQLQueryAST, index: DefinitionIndex): void {
    const visit = (node: unknown): void => {
        if (isLogical(node)) {
            node.conditions.forEach(visit);
        } else if (isCondition(node)) {
            validateCondition(node, index);
        }
    };
    visit(ast.expression);
}
