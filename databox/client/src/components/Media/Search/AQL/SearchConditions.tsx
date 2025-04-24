import {AQLQueries, AQLQuery, generateQueryId} from './query.ts';
import {Box, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SearchCondition from './SearchCondition.tsx';
import {useModals} from '@alchemy/navigation';
import SearchConditionDialog from './SearchConditionDialog.tsx';
import AddIcon from '@mui/icons-material/Add';
import {TResultContext} from '../ResultContext.tsx';
import {useResolveASTs} from './useResolveASTs.ts';
import {
    getIndexBySearchSlug,
    getIndexBySlug,
    useAttributeDefinitionStore,
} from '../../../../store/attributeDeifnitionStore.ts';

type Props = {
    conditions: AQLQueries;
    onDelete: (condition: AQLQuery) => void;
    onUpsert: (condition: AQLQuery) => void;
    result: TResultContext;
};

export default function SearchConditions({
    conditions,
    onDelete,
    onUpsert,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {load, loaded} = useAttributeDefinitionStore();
    const definitionsIndexBySlug = getIndexBySlug();
    const definitionsIndexBySearchSlug = getIndexBySearchSlug();

    React.useEffect(() => {
        if (!loaded) {
            load(t);
        }
    }, [loaded, t, load]);

    const asts = useResolveASTs({
        conditions,
        loaded,
        definitionsIndexBySlug,
        definitionsIndexBySearchSlug,
    });

    return (
        <Box
            sx={{
                mr: -1,
            }}
        >
            {asts.map(resolvedAst => {
                return (
                    <SearchCondition
                        key={resolvedAst.condition.id}
                        condition={resolvedAst.condition}
                        query={resolvedAst.query}
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
