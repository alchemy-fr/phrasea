import {AQLQueries, AQLQuery} from "./query.ts";
import {Box, Button} from "@mui/material";
import {useTranslation} from 'react-i18next';
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
    const {t} = useTranslation();
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
                        onUpsert={onUpsert}
                    />
                );
            })}
            <Button
                startIcon={<AddIcon />}
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
                {t('search_condition.add_condition', 'Add Condition')}
            </Button>
        </Box>
    );
}
