'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {
    DownloadIcon,
    LinkIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
    UnlinkIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, AssetAttachment} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {MoreVerticalIcon} from 'lucide-react';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {deleteAttachment, putAttachment, postAttachment} from '@/lib/api/misc';
import {deleteAssets, uploadAsset} from '@/lib/api/assets';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {FileOrUrlInput, FileOrUrl} from '@/components/form/FileOrUrlInput';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {EntityName} from '@/types/api';
import {iri} from '@/lib/utils/iri';

export function AssetAttachments({asset}: {asset: Asset}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const openAsset = useAssetOpener();
    const [renaming, setRenaming] = useState<{id: string; name: string} | null>(
        null
    );
    const attachments = asset.attachments ?? [];
    const refresh = () =>
        queryClient.invalidateQueries({queryKey: ['asset-view', asset.id]});

    const rename = async () => {
        if (!renaming) {
            return;
        }
        await putAttachment(renaming.id, {name: renaming.name});
        setRenaming(null);
        void refresh();
    };

    return (
        <div className="space-y-2">
            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('asset.attachments.empty', 'No attachment')}
                </p>
            ) : null}
            <ul className="space-y-1">
                {attachments.map((att: AssetAttachment) => (
                    <li
                        key={att.id}
                        className="flex items-center gap-2 rounded-md border p-1.5 text-sm"
                    >
                        <button
                            type="button"
                            className="size-10 shrink-0 overflow-hidden rounded bg-media-bg"
                            onClick={() => openAsset(att.attachment)}
                        >
                            <AssetThumb asset={att.attachment} size={40} />
                        </button>
                        {renaming?.id === att.id ? (
                            <Input
                                autoFocus
                                value={renaming.name}
                                className="h-7"
                                onChange={e =>
                                    setRenaming({
                                        id: att.id,
                                        name: e.target.value,
                                    })
                                }
                                onKeyDown={e => {
                                    if (e.key === 'Enter') void rename();
                                    if (e.key === 'Escape') setRenaming(null);
                                }}
                                onBlur={rename}
                            />
                        ) : (
                            <span className="min-w-0 flex-1 truncate">
                                {att.name ?? att.attachment.name}
                            </span>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon-xs">
                                    <MoreVerticalIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {att.attachment.source?.url ? (
                                    <DropdownMenuItem asChild>
                                        <a
                                            href={att.attachment.source.url}
                                            download
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <DownloadIcon />{' '}
                                            {t(
                                                'asset.actions.download',
                                                'Download'
                                            )}
                                        </a>
                                    </DropdownMenuItem>
                                ) : null}
                                <DropdownMenuItem
                                    onSelect={() =>
                                        setRenaming({
                                            id: att.id,
                                            name:
                                                att.name ??
                                                att.attachment.name ??
                                                '',
                                        })
                                    }
                                >
                                    <PencilIcon />{' '}
                                    {t('common.rename', 'Rename')}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() =>
                                        openModal(ConfirmDialog, {
                                            title: t(
                                                'asset.attachments.detach.title',
                                                'Detach this attachment?'
                                            ),
                                            description: t(
                                                'asset.attachments.detach.help',
                                                'The attached asset is kept.'
                                            ),
                                            onConfirm: async () => {
                                                await deleteAttachment(att.id);
                                                void refresh();
                                            },
                                        })
                                    }
                                >
                                    <UnlinkIcon />{' '}
                                    {t('asset.attachments.detach', 'Detach')}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() =>
                                        openModal(ConfirmDialog, {
                                            title: t(
                                                'asset.attachments.delete.title',
                                                'Delete this attachment?'
                                            ),
                                            destructive: true,
                                            onConfirm: async () => {
                                                await deleteAttachment(att.id);
                                                await deleteAssets([
                                                    att.attachment.id,
                                                ]);
                                                void refresh();
                                            },
                                        })
                                    }
                                >
                                    <Trash2Icon />{' '}
                                    {t('common.delete', 'Delete')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </li>
                ))}
            </ul>
            <Button
                variant="outline"
                size="sm"
                onClick={() =>
                    openModal(AddAttachmentDialog, {asset, onAdded: refresh})
                }
            >
                <PlusIcon /> {t('asset.attachments.add', 'Add attachment')}
            </Button>
        </div>
    );
}

function AddAttachmentDialog({
    open,
    onOpenChange,
    asset,
    onAdded,
}: ModalProps & {asset: Asset; onAdded: () => void}) {
    const {t} = useTranslation();
    const [value, setValue] = useState<FileOrUrl>({});
    const [name, setName] = useState('');
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        setLoading(true);
        try {
            const props = {
                workspace: iri(EntityName.Workspace, asset.workspace.id),
                relationship: {source: asset.id, type: 'attachment'},
            };
            let created;
            if (value.file) {
                created = await uploadAsset(value.file, {
                    ...props,
                    name: name || value.file.name,
                });
            } else {
                const {postAsset} = await import('@/lib/api/assets');
                created = await postAsset({
                    ...props,
                    name: name || value.url,
                    sourceFile: {url: value.url, importFile: true},
                });
            }
            await postAttachment({
                assetId: asset.id,
                attachmentId: created.id,
                name: name || undefined,
            });
            toast.success(t('asset.attachments.added', 'Attachment added'));
            onAdded();
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
                        {t('asset.attachments.add', 'Add attachment')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-3">
                    <Input
                        placeholder={t('common.name', 'Name')}
                        value={name}
                        onChange={e => setName(e.target.value)}
                    />
                    <FileOrUrlInput value={value} onChange={setValue} />
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
                        disabled={!value.file && !value.url}
                        loading={loading}
                    >
                        <LinkIcon /> {t('common.add', 'Add')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
