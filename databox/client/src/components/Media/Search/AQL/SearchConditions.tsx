import {AQLQueries, AQLQuery} from "./query.ts";
import {Box} from "@mui/material";
import AqlField from "./AQLField.tsx";
import React from "react";
import SearchCondition from "./SearchCondition.tsx";

type Props = {
    conditions: AQLQueries;
    onDelete: (condition: AQLQuery) => void;
    onUpdate: (condition: AQLQuery) => void;

};

export default function SearchConditions({
    conditions,
    onDelete,
    onUpdate,
}: Props) {

    return (
        <Box
            sx={{
                mr: -1,
            }}
        >
            <AqlField/>
            {conditions.map((condition: AQLQuery) => {
                return (
                    <SearchCondition
                        key={condition.id}
                        condition={condition}
                        onDelete={onDelete}
                        onUpdate={onUpdate}
                    />
                );
            })}
        </Box>
    );
}
