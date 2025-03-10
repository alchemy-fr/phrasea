import {AQLQuery} from "./query.ts";
import {Chip} from "@mui/material";

type Props = {
    condition: AQLQuery;
    onDelete: (condition: AQLQuery) => void;
    onUpdate: (condition: AQLQuery) => void;
};

export default function SearchCondition({
    condition,
}: Props) {

    return <>
        <Chip
            label={condition.query}
        />
    </>
}
