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
    value: ResolvedBucketValue[]
): string {
    if (type === 'missing') {
        return `${title} is missing`;
    }

    switch (widget) {
        default:
        case FacetType.Text:
            return `${title} = "${value
                .map(v => extractLabelValueFromKey(v, type).label)
                .join('" or "')}"`;
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

function Filter({t, x, i, v, w, onInvert, onDelete}: FilterProps) {
    return (
        <Chip
            sx={{
                mb: 1,
                mr: 1,
            }}
            title={`${i ? 'Not ' : ''}${formatFilterTitle(w, x, t, v)}`}
            label={`${i ? 'Not ' : ''}${formatFilterLabel(w, x, t, v)}`}
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
