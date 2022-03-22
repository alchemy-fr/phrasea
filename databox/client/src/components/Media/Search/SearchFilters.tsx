import React from 'react';
import {Chip} from "@mui/material";
import {FilterEntry, Filters, FilterValue} from "./Filter";

type FilterProps = {
    onInvert: () => void;
    onDelete: () => void;
} & FilterEntry;

function truncate(value: FilterValue, maxLength: number): FilterValue {
    if (typeof value === 'string' && value.length > maxLength) {
        return value.substring(0, maxLength-1)+'…';
    }

    return value;
}

function Filter({
                    t,
                    i,
                    v,
                    onInvert,
                    onDelete,
                }: FilterProps) {
    return <Chip
        title={`${t} = "${v.join('" or "')}"`}
        label={v.map(s => truncate(s, 15)).join(', ')}
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
    return <div>
        {filters.map((f, i) => {
            return <React.Fragment key={i}>
                {' '}
                <Filter
                    {...f}
                    onDelete={() => onDelete(i)}
                    onInvert={() => onInvert(i)}
                />
            </React.Fragment>
        })}
    </div>
}
