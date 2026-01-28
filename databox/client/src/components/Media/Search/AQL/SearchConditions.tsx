import {generateQueryId} from './query.ts';
import {Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SearchCondition from './SearchCondition.tsx';
import {useModals} from '@alchemy/navigation';
import SearchConditionDialog from './SearchConditionDialog.tsx';
import AddIcon from '@mui/icons-material/Add';
import {useResolveASTs} from './useResolveASTs.ts';
import {
    useAttributeDefinitionStore,
    useIndexBySearchSlug,
    useIndexBySlug,
} from '../../../../store/attributeDefinitionStore.ts';
import {TSearchContext} from '../SearchContext.tsx';

type Props = {
    search: TSearchContext;
};

export default function SearchConditions({search}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {load, loaded} = useAttributeDefinitionStore();
    const definitionsIndexBySlug = useIndexBySlug();
    const definitionsIndexBySearchSlug = useIndexBySearchSlug();

    React.useEffect(() => {
        if (!loaded) {
            load(t);
        }
    }, [loaded, t, load]);

    const asts = useResolveASTs({
        conditions: search.conditions,
        loaded,
        definitionsIndexBySlug,
        definitionsIndexBySearchSlug,
    });

    return (
        <>
            {asts.map(resolvedAst => {
                return (
                    <SearchCondition
                        key={resolvedAst.condition.id}
                        condition={resolvedAst.condition}
                        query={resolvedAst.query}
                        onDelete={search.removeCondition}
                        onUpsert={search.upsertCondition}
                    />
                );
            })}
            <Button
                startIcon={<AddIcon />}
                onClick={() => {
                    openModal(SearchConditionDialog, {
                        onUpsert: search.upsertCondition,
                        condition: {
                            id: generateQueryId(),
                            query: '',
                        },
                    });
                }}
            >
                {t('search_condition.add_condition', 'Add Condition')}
            </Button>
        </>
    );
}
