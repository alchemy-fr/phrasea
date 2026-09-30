import React from 'react';
import {AttributeDefinitionIndex} from '../../../../AttributeEditor/types.ts';
import {useTranslation} from 'react-i18next';
import {OperatorChoice, QBExpression} from './builderTypes.ts';
import {StateSetterHandler} from '../../../../../types.ts';
import {emptyCondition, removeExpression} from './builder.ts';
import ExpressionBuilder from './ExpressionBuilder.tsx';
import AddExpressionRow from './AddExpressionRow.tsx';
import {AQLOperator, RawType} from '../aqlTypes.ts';

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
    expression: QBExpression;
    setExpression: StateSetterHandler<QBExpression>;
};

export default function ConditionsBuilder({
    definitionsIndex,
    expression,
    setExpression,
}: Props) {
    const {t} = useTranslation();

    const gt = [RawType.Number, RawType.Date, RawType.DateTime];
    const text = [RawType.Keyword, RawType.String];
    const fullText = [RawType.String];
    const dates = (label: string) => ({
        [RawType.Date]: label,
        [RawType.DateTime]: label,
    });

    const operators: OperatorChoice[] = [
        {
            value: AQLOperator.EQ,
            label: t('search_condition.builder.operator.is', 'Is'),
            typeLabels: {[RawType.Number]: '='},
        },
        {
            value: AQLOperator.NEQ,
            label: t('search_condition.builder.operator.is_not', 'Is not'),
            typeLabels: {[RawType.Number]: '≠'},
        },
        {
            value: AQLOperator.LT,
            label: '<',
            typeLabels: dates(
                t('search_condition.builder.operator.is_before', 'Is before')
            ),
            supportedTypes: gt,
        },
        {
            value: AQLOperator.LTE,
            label: '≤',
            typeLabels: dates(
                t(
                    'search_condition.builder.operator.is_on_or_before',
                    'Is on or before'
                )
            ),
            supportedTypes: gt,
        },
        {
            value: AQLOperator.GT,
            label: '>',
            typeLabels: dates(
                t('search_condition.builder.operator.is_after', 'Is after')
            ),
            supportedTypes: gt,
        },
        {
            value: AQLOperator.GTE,
            label: '≥',
            typeLabels: dates(
                t(
                    'search_condition.builder.operator.is_on_or_after',
                    'Is on or after'
                )
            ),
            supportedTypes: gt,
        },
        {
            value: AQLOperator.CONTAINS,
            label: t('search_condition.builder.operator.contains', 'Contains'),
            supportedTypes: text,
        },
        {
            value: AQLOperator.NOT_CONTAINS,
            label: t(
                'search_condition.builder.operator.not_contains',
                'Does not contain'
            ),
            supportedTypes: text,
        },
        {
            value: AQLOperator.MATCHES,
            label: t(
                'search_condition.builder.operator.contains_words',
                'Contains words'
            ),
            supportedTypes: fullText,
        },
        {
            value: AQLOperator.NOT_MATCHES,
            label: t(
                'search_condition.builder.operator.not_contains_words',
                'Does not contain words'
            ),
            supportedTypes: fullText,
        },
        {
            value: AQLOperator.STARTS_WITH,
            label: t(
                'search_condition.builder.operator.starts_with',
                'Starts with'
            ),
            supportedTypes: text,
        },
        {
            value: AQLOperator.NOT_STARTS_WITH,
            label: t(
                'search_condition.builder.operator.not_starts_with',
                'Does not start with'
            ),
            supportedTypes: text,
        },
        {
            value: AQLOperator.ENDS_WITH,
            label: t(
                'search_condition.builder.operator.ends_with',
                'Ends with'
            ),
            supportedTypes: text,
        },
        {
            value: AQLOperator.NOT_ENDS_WITH,
            label: t(
                'search_condition.builder.operator.not_ends_with',
                'Does not end with'
            ),
            supportedTypes: text,
        },
        {
            value: AQLOperator.IN,
            label: t(
                'search_condition.builder.operator.is_any_of',
                'Is any of'
            ),
            manyArgs: true,
        },
        {
            value: AQLOperator.NOT_IN,
            label: t(
                'search_condition.builder.operator.is_none_of',
                'Is none of'
            ),
            manyArgs: true,
        },
        {
            value: AQLOperator.HAS_ALL_OF,
            label: t(
                'search_condition.builder.operator.has_all_of',
                'Has all of'
            ),
            manyArgs: true,
            multipleOnly: true,
        },
        {
            value: AQLOperator.BETWEEN,
            label: t(
                'search_condition.builder.operator.is_between',
                'Is between'
            ),
            typeLabels: dates(
                t('search_condition.builder.operator.is_within', 'Is within')
            ),
            manyArgs: 2,
            supportedTypes: gt,
        },
        {
            value: AQLOperator.NOT_BETWEEN,
            label: t(
                'search_condition.builder.operator.is_not_between',
                'Is not between'
            ),
            typeLabels: dates(
                t(
                    'search_condition.builder.operator.is_not_within',
                    'Is not within'
                )
            ),
            manyArgs: 2,
            supportedTypes: gt,
        },
        {
            value: AQLOperator.MISSING,
            label: t('search_condition.builder.operator.is_empty', 'Is empty'),
            manyArgs: 0,
        },
        {
            value: AQLOperator.EXISTS,
            label: t(
                'search_condition.builder.operator.is_not_empty',
                'Is not empty'
            ),
            manyArgs: 0,
        },
        {
            value: AQLOperator.WITHIN_CIRCLE,
            label: t(
                'search_condition.builder.operator.within_circle',
                'Is within circle'
            ),
            manyArgs: 3,
            argNames: [
                t(
                    'search_condition.builder.operator.within_circle_latitude',
                    'Latitude'
                ),
                t(
                    'search_condition.builder.operator.within_circle_longitude',
                    'Longitude'
                ),
                t(
                    'search_condition.builder.operator.within_circle_radius',
                    'Radius'
                ),
            ],
            supportedTypes: [RawType.GeoPoint],
        },
        {
            value: AQLOperator.WITHIN_RECTANGLE,
            label: t(
                'search_condition.builder.operator.within_rectangle',
                'Is within rectangle'
            ),
            manyArgs: 4,
            argNames: [
                t(
                    'search_condition.builder.operator.within_rectangle_top_left_latitude',
                    'Top Left Latitude'
                ),
                t(
                    'search_condition.builder.operator.within_rectangle_top_left_longitude',
                    'Top Left Longitude'
                ),
                t(
                    'search_condition.builder.operator.within_rectangle_bottom_right_latitude',
                    'Bottom Right Latitude'
                ),
                t(
                    'search_condition.builder.operator.within_rectangle_bottom_right_longitude',
                    'Bottom Right Longitude'
                ),
            ],
            supportedTypes: [RawType.GeoPoint],
        },
    ];

    return (
        <>
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

            <AddExpressionRow setExpression={setExpression} />
        </>
    );
}
