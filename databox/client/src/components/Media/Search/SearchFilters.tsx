import React from 'react';
import {AttrFilters} from "./SearchContextProvider";
import {Chip} from "@mui/material";

type FilterProps = {
    field: string;
    inverted: boolean;
    values: string[];
    onInvert: () => void;
    onDelete: () => void;
}

function Filter({
                    field,
                    values,
                    onInvert,
                    onDelete,
                    inverted,
                }: FilterProps) {
    return <Chip
        title={field}
        label={values.join(', ').substring(0, 30)}
        onDelete={onDelete}
        onClick={onInvert}
        color={inverted ? 'error' : 'primary'}
    />
}

type Props = {
    filters: AttrFilters;
    onInvert: (name: string) => void;
    onDelete: (name: string) => void;
};

export default function SearchFilters({
                                          filters,
                                          onDelete,
                                          onInvert,
                                      }: Props) {
    return <div>
        {Object.keys(filters).map(k => {
            const f = k.replace(/^-/, '');

            return <React.Fragment key={k}>
                {' '}
                <Filter
                    key={k}
                    field={f}
                    values={filters[k]}
                    onDelete={() => onDelete(k)}
                    onInvert={() => onInvert(f)}
                    inverted={k.startsWith('-')}
                />
            </React.Fragment>
        })}
    </div>
}
