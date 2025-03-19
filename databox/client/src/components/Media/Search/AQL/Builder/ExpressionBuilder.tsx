import React from "react";
import {BaseBuilderProps, QBAndOrExpression, QBCondition, QBExpression} from "./builderTypes.ts";
import {hasProp} from "../../../../../lib/utils.ts";
import AndOrOrExpressionBuilder from "./AndOrOrExpressionBuilder.tsx";
import ConditionBuilder from "./ConditionBuilder.tsx";
import {removeExpression} from "./builder.ts";
import {StateSetter} from "../../../../../types.ts";

type Props = {
    onRemove?: BaseBuilderProps<QBExpression>['onRemove'];
} & Omit<BaseBuilderProps<QBExpression>, 'onRemove'>;

export default function ExpressionBuilder({
    definitionsIndex,
    operators,
    expression,
    setExpression,
    onRemove: removeParent,
}: Props) {
    const onRemove = (expr: QBExpression) => {
        setExpression((p) => {
            return removeExpression(p, expr, removeParent ? () => removeParent!(expression) : undefined);
        })
    }

    if (hasProp<QBAndOrExpression>(expression, 'conditions')) {
        return <AndOrOrExpressionBuilder
            definitionsIndex={definitionsIndex}
            expression={expression}
            setExpression={setExpression as StateSetter<QBAndOrExpression>}
            onRemove={onRemove}
            operators={operators}
        />
    }

    return <ConditionBuilder
        definitionsIndex={definitionsIndex}
        operators={operators}
        onRemove={onRemove}
        expression={expression}
        setExpression={setExpression as StateSetter<QBCondition>}
    />
}
