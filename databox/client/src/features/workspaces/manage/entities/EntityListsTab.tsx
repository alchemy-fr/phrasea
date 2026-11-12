'use client';

import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import type {EntityList} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteEntityList,
    getEntityList,
    getEntityLists,
} from '@/lib/api/metadata';
import {Badge} from '@alchemy/next-ui/ui/misc';
import {useInfiniteList} from '@/hooks/useInfiniteList';
import {ExploreEntityListButton} from '@/features/entities/explore/ExploreEntityListButton';
import {EntityGrid} from './EntityGrid';
import {EntityListForm} from './EntityListForm';

export function EntityListsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const lists = useInfiniteList<EntityList>({
        queryKey: ['entity-lists', workspace.id, 'manage'],
        fetchPage: ({query, url, signal}) =>
            getEntityLists(
                {
                    workspaceId: workspace.id,
                    query: query || undefined,
                    limit: 50,
                    url,
                },
                signal
            ),
    });

    return (
        <DefinitionManager<EntityList>
            items={lists.items}
            loading={lists.loading}
            error={lists.error}
            onChanged={() =>
                queryClient.invalidateQueries({
                    queryKey: ['entity-lists', workspace.id],
                })
            }
            query={lists.query}
            onQueryChange={lists.setQuery}
            hasMore={lists.hasMore}
            loadingMore={lists.loadingMore}
            onReachEnd={lists.loadMore}
            getItem={getEntityList}
            itemQueryKey={['entity-lists', workspace.id, 'item']}
            renderItem={l => <span className="block truncate">{l.name}</span>}
            renderItemDetails={l =>
                l.allowNewValues ? (
                    <Badge variant="muted">
                        {t('entity_list.open', 'open')}
                    </Badge>
                ) : null
            }
            onDelete={l => deleteEntityList(l.id)}
            createLabel={t('entity_list.create', 'New list')}
            manageLabel={t('entity_list.manage', 'Values')}
            renderRowActions={l => (
                <ExploreEntityListButton
                    listId={l.id}
                    className="h-7 text-xs"
                />
            )}
            renderManage={(l, back) => (
                <EntityGrid list={l} workspace={workspace} onBack={back} />
            )}
            renderForm={(l, onSaved) => (
                <EntityListForm
                    key={l?.id ?? 'new'}
                    list={l}
                    workspaceId={workspace.id}
                    onSaved={onSaved}
                    withPermissions
                />
            )}
        />
    );
}
