import React from 'react';
import {Chip} from "@mui/material";
import {FilterEntry, Filters} from "./Filter";

type FilterProps = {
    onInvert: () => void;
    onDelete: () => void;
} & FilterEntry;

function Filter({
                    a,
                    t,
                    i,
                    v,
                    onInvert,
                    onDelete,
                }: FilterProps) {
    return <Chip
        title={t}
        label={v.join(', ').substring(0, 30)}
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
