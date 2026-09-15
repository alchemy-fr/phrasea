'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {ChevronRightIcon, FolderIcon, LayersIcon} from 'lucide-react';
import type {Collection} from '@/types/api';
import {EntityName, Privacy} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FormRow, Input} from '@/components/ui/input';
import {PrivacyField} from '@/components/form/PrivacyField';
import {postCollection} from '@/lib/api/collections';
import {useCollectionStore} from './collectionStore';
import {iri} from '@/lib/utils/iri';

type Props = ModalProps<Collection> & {
    workspaceId: string;
    parent?: Collection;
    onCreated?: (collection: Collection) => void;
};

export function CreateCollectionDialog({
    open,
    onOpenChange,
    resolve,
    workspaceId,
    parent,
    onCreated,
}: Props) {
    const {t} = useTranslation();
    const [name, setName] = useState('');
    const [privacy, setPrivacy] = useState<Privacy>(Privacy.Secret);
    const upsert = useCollectionStore(s => s.upsertCollection);
    const workspace = useCollectionStore(s =>
        s.workspaces.find(w => w.id === workspaceId)
    );

    const submit = async () => {
        const collection = await postCollection({
            name,
            privacy,
            parent: parent ? iri(EntityName.Collection, parent.id) : undefined,
            workspace: iri(EntityName.Workspace, workspaceId),
        });
        upsert(collection, workspaceId);
        toast.success(t('collections.created', 'Collection created'));
        onCreated?.(collection);
        resolve?.(collection);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('collections.create.title', 'New collection')}
            submitLabel={t('common.create', 'Create')}
            canSubmit={!!name.trim()}
            dirty={!!name.trim()}
            onSubmit={submit}
        >
            <div className="mb-4 flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                <LayersIcon className="size-3.5" />{' '}
                {workspace?.displayName ?? workspace?.name}
                {(parent?.absoluteDisplayName ?? parent?.displayName)
                    ?.split(' / ')
                    .map((p, i) => (
                        <span
                            key={i}
                            className="inline-flex items-center gap-1"
                        >
                            <ChevronRightIcon className="size-3" />
                            <FolderIcon className="size-3.5" /> {p}
                        </span>
                    ))}
            </div>
            <FormRow label={t('common.name', 'Name')} htmlFor="col-name">
                <Input
                    id="col-name"
                    autoFocus
                    value={name}
                    onChange={e => setName(e.target.value)}
                />
            </FormRow>
            <PrivacyField
                value={privacy}
                onChange={v => setPrivacy(v ?? Privacy.Secret)}
                inheritedPrivacy={parent?.privacy}
            />
        </FormDialog>
    );
}
