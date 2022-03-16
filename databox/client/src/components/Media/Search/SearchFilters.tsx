import {AttrFilters} from "./SearchContextProvider";
import {Chip} from "@mui/material";

type FilterProps = {
    title: string;
    values: string[];
    onDelete: () => void;
}

function Filter({
    title,
    values,
    onDelete,
                }: FilterProps) {
    return <Chip
        label={values.join(', ').substring(0, 30)}
        onDelete={onDelete}
        color="primary"
    />
}

type Props = {
    filters: AttrFilters;
    onDelete: (name: string) => void;
};

export default function SearchFilters({
                                          filters,
                                          onDelete,
}: Props) {
    return <div>
        {Object.keys(filters).map(k => {
            return <Filter
                key={k}
                title={k}
                values={filters[k]}
                onDelete={() => onDelete(k)}
            />
        })}
    </div>
}
