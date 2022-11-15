import React from 'react';
import {Box, Chip} from "@mui/material";
import {FilterEntry, Filters} from "./Filter";
import {BucketKeyValue, extractLabelValueFromKey, FacetType} from "../Asset/Facets";

type FilterProps = {
    onInvert: () => void;
    onDelete: () => void;
} & FilterEntry;

function truncate(value: string, maxLength: number): string {
    if (value.length > maxLength) {
        const pad = maxLength / 2;
        return value.substring(0, pad - 1) + '…' + value.substring(value.length - pad);
    }

    return value;
}

function formatFilterTitle(widget: FacetType | undefined, t: string, v: BucketKeyValue[]): string {
    switch (widget) {
        default:
        case FacetType.String:
            return `${t} = "${v.map(v => extractLabelValueFromKey(v).label).join('" or "')}"`;
        case FacetType.DateRange:
            return `${t} between ${extractLabelValueFromKey(v[0]).label} and ${extractLabelValueFromKey(v[1]).label}`;
    }
}

function formatFilterLabel(widget: FacetType | undefined, t: string, v: BucketKeyValue[]): string {
    switch (widget) {
        default:
        case FacetType.String:
            return v.map(s => truncate(extractLabelValueFromKey(s).label, 15)).join(', ');
        case FacetType.DateRange:
            return `${extractLabelValueFromKey(v[0]).label} - ${extractLabelValueFromKey(v[1]).label}`;
    }
}

function Filter({
                    t,
                    i,
                    v,
                    w,
                    onInvert,
                    onDelete,
                }: FilterProps) {
    return <Chip
        sx={{
            mb: 1,
            mr: 1,
        }}
        title={formatFilterTitle(w, t, v)}
        label={formatFilterLabel(w, t, v)}
        onDelete={onDelete}
        onClick={onInvert}
        color={i ? 'error' : 'primary'}
    />
}

type Props = {
    filters: Filters;
    onInvert: (key: number) => void;
    onDelete: (key: number) => void;
};

export default function SearchFilters({
                                          filters,
                                          onDelete,
                                          onInvert,
                                      }: Props) {
    return <Box sx={{
        mb: -1,
        mr: -1,
    }}>
        {filters.map((f, i) => {
            return <React.Fragment key={i}>
                <Filter
                    {...f}
                    onDelete={() => onDelete(i)}
                    onInvert={() => onInvert(i)}
                />
            </React.Fragment>
        })}
    </Box>
}
