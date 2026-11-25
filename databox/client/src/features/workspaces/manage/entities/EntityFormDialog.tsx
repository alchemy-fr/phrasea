'use client';

import {useTranslation} from 'react-i18next';
import type {AttributeEntity, EntityList} from '@/types/api';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {
    confirmLeave,
    UnsavedChangesScope,
    useUnsavedChangesChildScope,
} from '@alchemy/next-ui/navigation/unsavedChanges';
import {EntityForm} from './EntityForm';

/**
 * The full form of a value (every field at once), over the grid.
 * `entity` undefined with `loading`: the value edited is being fetched.
 */
export function EntityFormDialog({
    open,
    onOpenChange,
    entity,
    loading,
    notFound,
    list,
    locales,
    parent,
    onSaved,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entity?: AttributeEntity;
    loading?: boolean;
    notFound?: boolean;
    list: EntityList;
    locales: string[];
    /** A new value of a hierarchy: created under this node, `null` at the top level */
    parent?: AttributeEntity | null;
    onSaved: (entity: AttributeEntity) => void;
}) {
    const {t} = useTranslation();
    const scope = useUnsavedChangesChildScope();
    const close = () => {
        void confirmLeave(scope).then(leave => leave && onOpenChange(false));
    };
    const creating = !entity && !loading && !notFound;

    return (
        <Dialog open={open} onOpenChange={o => (o ? onOpenChange(o) : close())}>
            <DialogContent size="md" data-testid="entity-form-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {entity || loading || notFound
                            ? t('entity.edit_form', 'Edit…')
                            : t('entity.create', 'New value')}
                    </DialogTitle>
                    {creating && parent !== undefined ? (
                        <DialogDescription data-testid="entity-form-parent">
                            {parent
                                ? t(
                                      'entity_hierarchy.create_in',
                                      'In: {{path}}',
                                      {
                                          path: [
                                              ...(parent.path ?? []).map(
                                                  p => p.value
                                              ),
                                              parent.placeholder
                                                  ? t(
                                                        'entity_hierarchy.placeholder',
                                                        'empty level'
                                                    )
                                                  : parent.value,
                                          ].join(' / '),
                                      }
                                  )
                                : t(
                                      'entity_hierarchy.create_top',
                                      'At the top level'
                                  )}
                        </DialogDescription>
                    ) : null}
                </DialogHeader>
                {notFound ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'definition.not_found',
                            'This item does not exist anymore.'
                        )}
                    </p>
                ) : loading && !entity ? (
                    <InlineLoader />
                ) : (
                    <UnsavedChangesScope.Provider value={scope}>
                        <EntityForm
                            key={entity?.id ?? 'new'}
                            entity={entity}
                            list={list}
                            locales={locales}
                            parent={parent}
                            onSaved={onSaved}
                            onCancel={close}
                        />
                    </UnsavedChangesScope.Provider>
                )}
            </DialogContent>
        </Dialog>
    );
}
