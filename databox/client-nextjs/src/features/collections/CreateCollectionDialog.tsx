'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {ChevronRightIcon, FolderIcon, LayersIcon} from 'lucide-react';
import type {Collection} from '@/types/api';
import {EntityName, Privacy} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {PrivacyField} from '@/components/form/PrivacyField';
import {postCollection} from '@/lib/api/collections';
import {useCollectionStore} from './collectionStore';
import {iri} from '@/lib/utils/iri';

type Props = ModalProps & {
    workspaceId: string;
    parent?: Collection;
    onCreated?: (collection: Collection) => void;
};

export function CreateCollectionDialog({
    open,
    onOpenChange,
    workspaceId,
    parent,
    onCreated,
}: Props) {
    const {t} = useTranslation();
    const [name, setName] = useState('');
    const [privacy, setPrivacy] = useState<Privacy>(Privacy.Secret);
    const [loading, setLoading] = useState(false);
    const upsert = useCollectionStore(s => s.upsertCollection);
    const workspace = useCollectionStore(s =>
        s.workspaces.find(w => w.id === workspaceId)
    );

    const submit = async () => {
        setLoading(true);
        try {
            const collection = await postCollection({
                name,
                privacy,
                parent: parent
                    ? iri(EntityName.Collection, parent.id)
                    : undefined,
                workspace: iri(EntityName.Workspace, workspaceId),
            });
            upsert(collection, workspaceId);
            toast.success(t('collections.created', 'Collection created'));
            onCreated?.(collection);
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('collections.create.title', 'New collection')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody>
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
                    <FormRow
                        label={t('common.name', 'Name')}
                        htmlFor="col-name"
                    >
                        <Input
                            id="col-name"
                            autoFocus
                            value={name}
                            onChange={e => setName(e.target.value)}
                            onKeyDown={e => {
                                if (e.key === 'Enter' && name.trim()) {
                                    void submit();
                                }
                            }}
                        />
                    </FormRow>
                    <PrivacyField
                        value={privacy}
                        onChange={v => setPrivacy(v ?? Privacy.Secret)}
                        inheritedPrivacy={parent?.privacy}
                    />
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!name.trim()}
                        loading={loading}
                    >
                        {t('common.create', 'Create')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
