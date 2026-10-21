'use client';

import {useTranslation} from 'react-i18next';
import type {AttributeEntity, EntityList} from '@/types/api';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {InlineLoader} from '@/components/ui/loader';
import {
    confirmLeave,
    UnsavedChangesScope,
    useUnsavedChangesChildScope,
} from '@/lib/navigation/unsavedChanges';
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
    onSaved,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    entity?: AttributeEntity;
    loading?: boolean;
    notFound?: boolean;
    list: EntityList;
    locales: string[];
    onSaved: (entity: AttributeEntity) => void;
}) {
    const {t} = useTranslation();
    const scope = useUnsavedChangesChildScope();
    const close = () => {
        void confirmLeave(scope).then(leave => leave && onOpenChange(false));
    };

    return (
        <Dialog open={open} onOpenChange={o => (o ? onOpenChange(o) : close())}>
            <DialogContent size="md" data-testid="entity-form-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {entity || loading || notFound
                            ? t('entity.edit_form', 'Edit…')
                            : t('entity.create', 'New value')}
                    </DialogTitle>
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
                            onSaved={onSaved}
                            onCancel={close}
                        />
                    </UnsavedChangesScope.Provider>
                )}
            </DialogContent>
        </Dialog>
    );
}
