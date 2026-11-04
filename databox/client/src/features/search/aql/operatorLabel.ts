import type {useTranslation} from 'react-i18next';
import {AQLOperator, RawType} from './types';

type TFunction = ReturnType<typeof useTranslation>['t'];

/**
 * Human-readable label of an operator (Airtable-like vocabulary),
 * depending on the type of the field it applies to.
 * The AQL syntax itself is given by `operatorLabels` (serializer).
 */
export function getOperatorLabel(
    t: TFunction,
    operator: AQLOperator,
    rawType?: RawType
): string {
    const isDate = rawType === RawType.Date || rawType === RawType.DateTime;
    const isNumber = rawType === RawType.Number;

    switch (operator) {
        case AQLOperator.EQ:
            return isNumber ? '=' : t('search.condition.operator.is', 'Is');
        case AQLOperator.NEQ:
            return isNumber
                ? '≠'
                : t('search.condition.operator.is_not', 'Is not');
        case AQLOperator.LT:
            return isDate
                ? t('search.condition.operator.is_before', 'Is before')
                : '<';
        case AQLOperator.LTE:
            return isDate
                ? t(
                      'search.condition.operator.is_on_or_before',
                      'Is on or before'
                  )
                : '≤';
        case AQLOperator.GT:
            return isDate
                ? t('search.condition.operator.is_after', 'Is after')
                : '>';
        case AQLOperator.GTE:
            return isDate
                ? t(
                      'search.condition.operator.is_on_or_after',
                      'Is on or after'
                  )
                : '≥';
        case AQLOperator.IN:
            return t('search.condition.operator.is_any_of', 'Is any of');
        case AQLOperator.NOT_IN:
            return t('search.condition.operator.is_none_of', 'Is none of');
        case AQLOperator.HAS_ALL_OF:
            return t('search.condition.operator.has_all_of', 'Has all of');
        case AQLOperator.MISSING:
            return t('search.condition.operator.is_empty', 'Is empty');
        case AQLOperator.EXISTS:
            return t('search.condition.operator.is_not_empty', 'Is not empty');
        case AQLOperator.CONTAINS:
            return t('search.condition.operator.contains', 'Contains');
        case AQLOperator.NOT_CONTAINS:
            return t(
                'search.condition.operator.not_contains',
                'Does not contain'
            );
        case AQLOperator.MATCHES:
            return t(
                'search.condition.operator.contains_words',
                'Contains words'
            );
        case AQLOperator.NOT_MATCHES:
            return t(
                'search.condition.operator.not_contains_words',
                'Does not contain words'
            );
        case AQLOperator.STARTS_WITH:
            return t('search.condition.operator.starts_with', 'Starts with');
        case AQLOperator.NOT_STARTS_WITH:
            return t(
                'search.condition.operator.not_starts_with',
                'Does not start with'
            );
        case AQLOperator.ENDS_WITH:
            return t('search.condition.operator.ends_with', 'Ends with');
        case AQLOperator.NOT_ENDS_WITH:
            return t(
                'search.condition.operator.not_ends_with',
                'Does not end with'
            );
        case AQLOperator.BETWEEN:
            return isDate
                ? t('search.condition.operator.is_within', 'Is within')
                : t('search.condition.operator.is_between', 'Is between');
        case AQLOperator.NOT_BETWEEN:
            return isDate
                ? t('search.condition.operator.is_not_within', 'Is not within')
                : t(
                      'search.condition.operator.is_not_between',
                      'Is not between'
                  );
        case AQLOperator.WITHIN_CIRCLE:
            return t(
                'search.condition.operator.within_circle',
                'Is within circle'
            );
        case AQLOperator.WITHIN_RECTANGLE:
            return t(
                'search.condition.operator.within_rectangle',
                'Is within rectangle'
            );
    }
}
