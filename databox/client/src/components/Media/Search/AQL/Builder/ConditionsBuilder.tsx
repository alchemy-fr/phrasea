import React from "react";
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";
import {useTranslation} from 'react-i18next';
import {OperatorChoice, QBExpression} from "./builderTypes.ts";
import {StateSetterHandler} from "../../../../../types.ts";
import {emptyCondition, removeExpression} from "./builder.ts";
import ExpressionBuilder from "./ExpressionBuilder.tsx";
import AddExpressionRow from "./AddExpressionRow.tsx";
import {AQLOperator, ManyArgs, RawType} from "../aqlTypes.ts";

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
    expression: QBExpression;
    setExpression: StateSetterHandler<QBExpression>;
};

export default function ConditionsBuilder({definitionsIndex, expression, setExpression}: Props) {
    const {t} = useTranslation();

    const operators: OperatorChoice[] = [
        {
            value: '=',
            label: t('search_condition.builder.operator.equals', '= (Equals)'),
        },
        {
            value: '!=',
            label: t('search_condition.builder.operator.not_equals', '!= (Not equals)'),
        },
        {
            value: '>',
            label: t('search_condition.builder.operator.greater_than', '> (Greater than)'),
        },
        {
            value: '>=',
            label: t('search_condition.builder.operator.greater_than_or_equals', '>= (Greater than or equals)'),
        },
        {
            value: '<',
            label: t('search_condition.builder.operator.less_than', '< (Less than)'),
        },
        {
            value: '<=',
            label: t('search_condition.builder.operator.less_than_or_equals', '<= (Less than or equals)'),
        },
        {
            value: 'CONTAINS',
            label: t('search_condition.builder.operator.contains', 'Contains'),
            supportedTypes: [RawType.String],
        },
        {
            value: 'NOT_CONTAINS',
            label: t('search_condition.builder.operator.not_contains',  `Doesn't Contain`),
            supportedTypes: [RawType.String],
        },
        {
            value: 'MATCHES',
            label: t('search_condition.builder.operator.matches', 'Matches'),
            supportedTypes: [RawType.String],
        },
        {
            value: 'NOT_MATCHES',
            label: t('search_condition.builder.operator.not_matches', `Doesn't Match`),
            supportedTypes: [RawType.String],
        },
        {
            value: 'STARTS_WITH',
            label: t('search_condition.builder.operator.starts_with', 'Starts With'),
            supportedTypes: [RawType.String],
        },
        {
            value: 'NOT_STARTS_WITH',
            label: t('search_condition.builder.operator.not_starts_with', `Doesn't Start With`),
            supportedTypes: [RawType.String],
        },
        {
            value: 'IN',
            label: t('search_condition.builder.operator.in', 'In'),
            manyArgs: true,
        },
        {
            value: 'NOT_IN',
            label: t('search_condition.builder.operator.not_in', 'Not In'),
            manyArgs: true,
        },
        {
            value: 'BETWEEN',
            label: t('search_condition.builder.operator.between', 'Between'),
            manyArgs: 2,
            supportedTypes: [RawType.Number, RawType.Date],
        },
        {
            value: 'NOT_BETWEEN',
            label: t('search_condition.builder.operator.not_between', 'Not Between'),
            manyArgs: 2,
            supportedTypes: [RawType.Number, RawType.Date],
        },
        {
            value: 'EXISTS',
            label: t('search_condition.builder.operator.exists', 'Exists'),
            manyArgs: 0,
        },
        {
            value: 'MISSING',
            label: t('search_condition.builder.operator.missing', 'Missing'),
            manyArgs: 0,
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
