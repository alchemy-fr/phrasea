import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";
import {useTranslation} from 'react-i18next';
import {Button, IconButton} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import {QBExpression} from "./builderTypes.ts";
import {StateSetterHandler} from "../../../../../types.ts";
import {addExpression, emptyCondition, removeExpression} from "./builder.ts";
import ExpressionBuilder from "./ExpressionBuilder.tsx";
import {hasProp} from "../../../../../lib/utils.ts";
import AddExpressionRow from "./AddExpressionRow.tsx";

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
    expression: QBExpression;
    setExpression: StateSetterHandler<QBExpression>;
};

export default function ConditionsBuilder({definitionsIndex, expression, setExpression}: Props) {
    const {t} = useTranslation();

    const operators = [
        {
            value: '=',
            label: t('search_condition.builder.operator.equals', 'Equals (=)'),
        },
        {
            value: '!=',
            label: t('search_condition.builder.operator.not_equals', 'Not equals (!=)'),
        },
        {
            value: '>',
            label: t('search_condition.builder.operator.greater_than', 'Greater than (>)'),
        },
        {
            value: '>=',
            label: t('search_condition.builder.operator.greater_than_or_equals', 'Greater than or equals (>=)'),
        },
        {
            value: '<',
            label: t('search_condition.builder.operator.less_than', 'Less than (<)'),
        },
        {
            value: '<=',
            label: t('search_condition.builder.operator.less_than_or_equals', 'Less than or equals (<=)'),
        },
        {
            value: 'CONTAINS',
            label: t('search_condition.builder.operator.contains', 'Contains'),
        },
        {
            value: 'MATCHES',
            label: t('search_condition.builder.operator.matches', 'Matches'),
        },
    ];

    return <>
        <ExpressionBuilder
            setExpression={setExpression}
            expression={expression}
            operators={operators}
            definitionsIndex={definitionsIndex}
            onRemove={expr => {
                return setExpression(p => {
                    return removeExpression(p, expr) || {...emptyCondition};
                });
            }}
        />

        <AddExpressionRow setExpression={setExpression}/>
    </>
}
