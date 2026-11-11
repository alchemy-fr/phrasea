'use client';

import {useState} from 'react';
import Link from 'next/link';
import {useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {CompassIcon, ListIcon, SettingsIcon} from 'lucide-react';
import type {EntityList} from '@/types/api';
import {getEntityList} from '@/lib/api/metadata';
import {getWorkspace} from '@/lib/api/collections';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {routes} from '@/lib/routes';
import {idFromIri} from '@/lib/utils/iri';
import {Button} from '@alchemy/next-ui/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {EmptyState} from '@alchemy/next-ui/ui/misc';
import {
    confirmLeave,
    UnsavedChangesScope,
    useUnsavedChangesChildScope,
} from '@alchemy/next-ui/navigation/unsavedChanges';
import {usePageTrail} from '@/components/layout/layoutStore';
import {EntityGrid} from '@/features/workspaces/manage/entities/EntityGrid';
import {EntityListForm} from '@/features/workspaces/manage/entities/EntityListForm';

/**
 * Full-page management of an entity list, for whoever may manage its values
 * (`capabilities.editValues`), workspace editor or not: the values grid, and
 * the list properties for its operators (`capabilities.edit`). Deleting the
 * list and granting these rights stay in the workspace management.
 */
export function EntityListManageScreen({listId}: {listId: string}) {
    return (
        <RequireAuth>
            <ManageList listId={listId} />
        </RequireAuth>
    );
}

function ManageList({listId}: {listId: string}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const [propertiesOpen, setPropertiesOpen] = useState(false);
    const scope = useUnsavedChangesChildScope();

    const listQueryKey = ['entity-list', listId];
    const list = useQuery({
        queryKey: listQueryKey,
        queryFn: () => getEntityList(listId),
    });
    const workspaceId =
        typeof list.data?.workspace === 'string'
            ? idFromIri(list.data.workspace)
            : list.data?.workspace?.id;
    const canManage = !!list.data?.capabilities?.editValues;
    const workspace = useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: () => getWorkspace(workspaceId!),
        enabled: !!workspaceId && canManage,
    });

    usePageTrail(
        list.data?.name ?? t('entity_list.manage_screen.title', 'Manage list'),
        routes.entityListManage(listId)
    );

    const closeProperties = () => {
        void confirmLeave(scope).then(
            leave => leave && setPropertiesOpen(false)
        );
    };

    const onSaved = (saved: EntityList) => {
        queryClient.setQueryData(listQueryKey, saved);
        void queryClient.invalidateQueries({queryKey: ['entity-lists']});
        setPropertiesOpen(false);
    };

    if (list.isError) {
        return (
            <EmptyState
                className="h-full"
                testId="entity-manage-not-found"
                icon={<ListIcon />}
                title={t(
                    'entity_list.explore.not_found',
                    'This list does not exist or you cannot see it.'
                )}
            />
        );
    }

    if (!list.data) {
        return <InlineLoader />;
    }

    if (!canManage) {
        return (
            <EmptyState
                className="h-full"
                testId="entity-manage-denied"
                icon={<ListIcon />}
                title={t(
                    'entity_list.manage_screen.denied',
                    'You are not allowed to manage this list'
                )}
                action={
                    <Button asChild variant="outline" size="sm">
                        <Link href={routes.entityListExplore(listId)}>
                            <CompassIcon />{' '}
                            {t(
                                'entity_list.manage_screen.explore',
                                'Explore the values'
                            )}
                        </Link>
                    </Button>
                }
            />
        );
    }

    if (!workspace.data) {
        return <InlineLoader />;
    }

    return (
        <div
            className="flex h-full min-h-0 flex-col gap-2 p-3"
            data-testid="entity-manage-screen"
        >
            {list.data.capabilities?.edit ? (
                <div className="flex justify-end">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setPropertiesOpen(true)}
                        data-testid="entity-manage-properties"
                    >
                        <SettingsIcon />{' '}
                        {t(
                            'entity_list.manage_screen.properties',
                            'Properties'
                        )}
                    </Button>
                </div>
            ) : null}
            <div className="min-h-0 flex-1">
                <EntityGrid
                    list={list.data}
                    workspace={workspace.data}
                    onBack={() => router.push(routes.entityListExplore(listId))}
                />
            </div>
            <Dialog
                open={propertiesOpen}
                onOpenChange={o =>
                    o ? setPropertiesOpen(o) : closeProperties()
                }
            >
                <DialogContent size="md">
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'entity_list.manage_screen.properties',
                                'Properties'
                            )}
                        </DialogTitle>
                    </DialogHeader>
                    <UnsavedChangesScope.Provider value={scope}>
                        <EntityListForm
                            key={list.data.updatedAt}
                            list={list.data}
                            workspaceId={workspace.data.id}
                            onSaved={onSaved}
                        />
                    </UnsavedChangesScope.Provider>
                </DialogContent>
            </Dialog>
        </div>
    );
}
