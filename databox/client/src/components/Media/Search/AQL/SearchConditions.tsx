import {AQLQueries, AQLQuery, generateQueryId} from './query.ts';
import {Box, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SearchCondition from './SearchCondition.tsx';
import {useModals} from '@alchemy/navigation';
import SearchConditionDialog from './SearchConditionDialog.tsx';
import AddIcon from '@mui/icons-material/Add';
import {TResultContext} from '../ResultContext.tsx';

type Props = {
    conditions: AQLQueries;
    onDelete: (condition: AQLQuery) => void;
    onUpsert: (condition: AQLQuery) => void;
    result: TResultContext;
};

export default function SearchConditions({
    result,
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
                        result={result}
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
                            id: generateQueryId(),
                            query: '',
                        },
                    });
                }}
            >
                {t('search_condition.add_condition', 'Add Condition')}
            </Button>
        </Box>
    );
}
