import React from 'react';
import {Chip} from "@mui/material";
import {FilterEntry, Filters} from "./Filter";
import {extractLabelValueFromKey} from "../Asset/Facets";

type FilterProps = {
    onInvert: () => void;
    onDelete: () => void;
} & FilterEntry;

function truncate(value: string, maxLength: number): string {
    if (value.length > maxLength) {
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
        title={`${t} = "${v.map(v => extractLabelValueFromKey(v).label).join('" or "')}"`}
        label={v.map(s => truncate(extractLabelValueFromKey(s).label, 15)).join(', ')}
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
