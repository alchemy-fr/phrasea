import {QBAndOrExpression, QBCondition, QBExpression} from "./builderTypes.ts";
import {hasProp} from "../../../../../lib/utils.ts";

export const emptyCondition: QBCondition = {
    leftOperand: {
        field: '',
    },
    operator: '=',
    rightOperand: {literal: ''},
};

export function addExpression(prev: QBExpression, group: boolean): QBAndOrExpression {
    const newExpression: QBAndOrExpression = hasProp<QBAndOrExpression>(prev, 'conditions') ? {
        ...prev,
        conditions: [...prev.conditions],
    } : {
        operator: 'AND',
        conditions: [prev]
    };

    newExpression.conditions.push(group ? {
        operator: 'AND',
        conditions: [
            {...emptyCondition},
        ]
    } : {...emptyCondition});

    return newExpression;
}

export function removeExpression(prev: QBExpression, expressionToRemove: QBExpression): QBExpression | null {
    if (hasProp<QBAndOrExpression>(prev, 'conditions')) {
        if (prev.conditions.length === 1) {
            return null;
        }

        return {
            ...prev,
            conditions: prev.conditions.filter(c => c !== expressionToRemove),
        };
    } else {
        return null;
    }
}

export function matchesNumber(value: string): boolean {
    return value.match(/^\d+([.,]\d+)?$/) !== null;
}

export function matchesInt(value: string): boolean {
    return value.match(/^\d+$/) !== null;
}

export function matchesFloat(value: string): boolean {
    return value.match(/^\d+([.,]\d+)?$/) !== null;
}
