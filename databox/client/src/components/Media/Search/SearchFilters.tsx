import {Box, Chip} from '@mui/material';
import {FilterEntry, Filters, FilterType} from './Filter';
import {
    extractLabelValueFromKey,
    FacetType,
    ResolvedBucketValue,
} from '../Asset/Facets';
import {AttributeType} from '../../../api/attributes';
import {DateFormats} from '../Asset/Attribute/types/DateType';
import React from 'react';
import {useTranslation} from 'react-i18next';
import type {TFunction} from '@alchemy/i18n';

type FilterProps = {
    onInvert: () => void;
    onDelete: () => void;
} & FilterEntry;

function truncate(value: string, maxLength: number): string {
    if (value.length > maxLength) {
        const pad = maxLength / 2;
        return (
            value.substring(0, pad - 1) +
            '…' +
            value.substring(value.length - pad)
        );
    }

    return value;
}

function formatFilterTitle(
    widget: FacetType | undefined,
    type: FilterType | undefined,
    title: string,
    value: ResolvedBucketValue[],
    t: TFunction
): string {
    if (type === 'missing') {
        return t('filter.is_missing', {
            defaultValue: '{{title}} is missing',
            title,
        });
    }

    switch (widget) {
        default:
        case FacetType.Text:
            return `${title} = "${value
                .map(v => extractLabelValueFromKey(v, type).label)
                .join(`" ${t('common.or', `or`)} "`)}"`;
        case FacetType.DateRange:
            return `${title} between ${
                extractLabelValueFromKey(value[0], type, DateFormats.Long).label
            } and ${
                extractLabelValueFromKey(value[1], type, DateFormats.Long).label
            }`;
    }
}

function formatFilterLabel(
    widget: FacetType | undefined,
    type: FilterType | undefined,
    title: string,
    value: ResolvedBucketValue[]
): string {
    if (type === AttributeType.Boolean) {
        return `${title}: ${extractLabelValueFromKey(value[0], type).label}`;
    }

    if (type === 'missing') {
        return `${title}: missing`;
    }

    switch (widget) {
        default:
            return value
                .map(s => truncate(extractLabelValueFromKey(s, type).label, 15))
                .join(', ');
        case FacetType.DateRange:
            return `${
                extractLabelValueFromKey(value[0], type, DateFormats.Short)
                    .label
            } - ${
                extractLabelValueFromKey(value[1], type, DateFormats.Short)
                    .label
            }`;
    }
}

function Filter({t: type, x, i, v, w, onInvert, onDelete}: FilterProps) {
    const {t} = useTranslation();
    return (
        <Chip
            sx={{
                mb: 1,
                mr: 1,
            }}
            title={`${i ? t('filter.exclude', `Exclude `) : ''}${formatFilterTitle(w, x, type, v, t)}`}
            label={`${i ? t('filter.exclude', `Exclude `) : ''}${formatFilterLabel(w, x, type, v)}`}
            onDelete={onDelete}
            onClick={onInvert}
            color={i ? 'error' : 'primary'}
        />
    );
}

type Props = {
    filters: Filters;
    onInvert: (key: number) => void;
    onDelete: (key: number) => void;
};

export default function SearchFilters({filters, onDelete, onInvert}: Props) {
    return (
        <Box
            sx={{
                mr: -1,
            }}
        >
            {filters.map((f, i) => {
                return (
                    <React.Fragment key={i}>
                        <Filter
                            {...f}
                            onDelete={() => onDelete(i)}
                            onInvert={() => onInvert(i)}
                        />
                    </React.Fragment>
                );
            })}
        </Box>
    );
}
