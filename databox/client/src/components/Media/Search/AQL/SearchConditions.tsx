import {AQLQueries, AQLQuery} from "./query.ts";
import {Box, IconButton} from "@mui/material";
import React from "react";
import SearchCondition from "./SearchCondition.tsx";
import {useModals} from "@alchemy/navigation";
import SearchConditionDialog from "./SearchConditionDialog.tsx";
import AddIcon from "@mui/icons-material/Add";

type Props = {
    conditions: AQLQueries;
    onDelete: (condition: AQLQuery) => void;
    onUpsert: (condition: AQLQuery) => void;
};

export default function SearchConditions({
    conditions,
    onDelete,
    onUpsert,
}: Props) {
    const {openModal} = useModals();

    return (
        <Box
            sx={{
                mr: -1,
            }}
        >
            {conditions.map((condition: AQLQuery) => {
                return (
                    <SearchCondition
                        key={condition.id}
                        condition={condition}
                        onDelete={onDelete}
                        onUpdate={onUpsert}
                    />
                );
            })}
            <IconButton
                onClick={() => {
                    openModal(SearchConditionDialog, {
                        onUpsert,
                        condition: {
                            id: Math.random().toString(36).substring(7),
                            query: '',
                        }
                    });
                }}
            >
                <AddIcon />
            </IconButton>
        </Box>
    );
}
