import {QBAndOrExpression, QBCondition, QBExpression, RemoveExpressionHandler} from "./builderTypes.ts";
import {hasProp} from "../../../../../lib/utils.ts";

export const emptyCondition: QBCondition = {
    leftOperand: {
        field: '',
    },
    operator: '',
    rightOperand: {literal: ''},
};

export function addExpression(prev: QBExpression): QBAndOrExpression {
    const newExpression: QBAndOrExpression = hasProp<QBAndOrExpression>(prev, 'conditions') ? {
            ...prev,
            conditions: [...prev.conditions],
        } : {
            operator: 'AND',
            conditions: [prev]
        };

    newExpression.conditions.push(emptyCondition);

    return newExpression;
}

export function removeExpression(prev: QBExpression, expression: QBExpression, removeParent?: () => void): QBExpression {
    if (hasProp<QBAndOrExpression>(prev, 'conditions')) {
        if (prev.conditions.length === 1) {
            removeParent?.();

            return prev;
        }

        return {
            ...prev,
            conditions: prev.conditions.filter(c => c !== expression),
        };
    } else {
        removeParent?.();
    }
    return prev;
}
