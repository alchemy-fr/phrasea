import React from "react";
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";
import {useTranslation} from 'react-i18next';
import {OperatorChoice, QBExpression} from "./builderTypes.ts";
import {StateSetterHandler} from "../../../../../types.ts";
import {emptyCondition, removeExpression} from "./builder.ts";
import ExpressionBuilder from "./ExpressionBuilder.tsx";
import AddExpressionRow from "./AddExpressionRow.tsx";
import {AQLOperator, RawType} from "../aqlTypes.ts";

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
    expression: QBExpression;
    setExpression: StateSetterHandler<QBExpression>;
};

export default function ConditionsBuilder({definitionsIndex, expression, setExpression}: Props) {
    const {t} = useTranslation();

    const operators: OperatorChoice[] = [
        {
            value: AQLOperator.EQ,
            label: t('search_condition.builder.operator.equals', '= (Equals)'),
        },
        {
            value: AQLOperator.NEQ,
            label: t('search_condition.builder.operator.not_equals', '!= (Not equals)'),
        },
        {
            value: AQLOperator.GT,
            label: t('search_condition.builder.operator.greater_than', '> (Greater than)'),
        },
        {
            value: AQLOperator.GTE,
            label: t('search_condition.builder.operator.greater_than_or_equals', '>= (Greater than or equals)'),
        },
        {
            value: AQLOperator.LT,
            label: t('search_condition.builder.operator.less_than', '< (Less than)'),
        },
        {
            value: AQLOperator.LTE,
            label: t('search_condition.builder.operator.less_than_or_equals', '<= (Less than or equals)'),
        },
        {
            value: AQLOperator.CONTAINS,
            label: t('search_condition.builder.operator.contains', 'Contains'),
            supportedTypes: [RawType.String],
        },
        {
            value: AQLOperator.NOT_CONTAINS,
            label: t('search_condition.builder.operator.not_contains',  `Doesn't Contain`),
            supportedTypes: [RawType.String],
        },
        {
            value: AQLOperator.MATCHES,
            label: t('search_condition.builder.operator.matches', 'Matches'),
            supportedTypes: [RawType.String],
        },
        {
            value: AQLOperator.NOT_MATCHES,
            label: t('search_condition.builder.operator.not_matches', `Doesn't Match`),
            supportedTypes: [RawType.String],
        },
        {
            value: AQLOperator.STARTS_WITH,
            label: t('search_condition.builder.operator.starts_with', 'Starts With'),
            supportedTypes: [RawType.String],
        },
        {
            value: AQLOperator.NOT_STARTS_WITH,
            label: t('search_condition.builder.operator.not_starts_with', `Doesn't Start With`),
            supportedTypes: [RawType.String],
        },
        {
            value: AQLOperator.IN,
            label: t('search_condition.builder.operator.in', 'In'),
            manyArgs: true,
        },
        {
            value: AQLOperator.NOT_IN,
            label: t('search_condition.builder.operator.not_in', 'Not In'),
            manyArgs: true,
        },
        {
            value: AQLOperator.BETWEEN,
            label: t('search_condition.builder.operator.between', 'Between'),
            manyArgs: 2,
            supportedTypes: [RawType.Number, RawType.Date],
        },
        {
            value: AQLOperator.NOT_BETWEEN,
            label: t('search_condition.builder.operator.not_between', 'Not Between'),
            manyArgs: 2,
            supportedTypes: [RawType.Number, RawType.Date],
        },
        {
            value: AQLOperator.EXISTS,
            label: t('search_condition.builder.operator.exists', 'Exists'),
            manyArgs: 0,
        },
        {
            value: AQLOperator.MISSING,
            label: t('search_condition.builder.operator.missing', 'Missing'),
            manyArgs: 0,
        },
        {
            value: AQLOperator.WITHIN_CIRCLE,
            label: t('search_condition.builder.operator.within_circle', 'Within Circle'),
            manyArgs: 3,
            argNames: [
                t('search_condition.builder.operator.within_circle_latitude', 'Latitude'),
                t('search_condition.builder.operator.within_circle_longitude', 'Longitude'),
                t('search_condition.builder.operator.within_circle_radius', 'Radius'),
            ],
            supportedTypes: [RawType.GeoPoint],
        },
        {
            value: AQLOperator.WITHIN_RECTANGLE,
            label: t('search_condition.builder.operator.within_rectangle', 'Within Rectangle'),
            manyArgs: 4,
            argNames: [
                t('search_condition.builder.operator.within_rectangle_top_left_latitude', 'Top Left Latitude'),
                t('search_condition.builder.operator.within_rectangle_top_left_longitude', 'Top Left Longitude'),
                t('search_condition.builder.operator.within_rectangle_bottom_right_latitude', 'Bottom Right Latitude'),
                t('search_condition.builder.operator.within_rectangle_bottom_right_longitude', 'Bottom Right Longitude'),
            ],
            supportedTypes: [RawType.GeoPoint],
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
