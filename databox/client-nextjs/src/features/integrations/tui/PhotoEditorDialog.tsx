'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {Progress} from '@/components/ui/misc';
import {multipartUpload} from '@/lib/api/upload';
import {runIntegrationAction} from '@/lib/api/integrations';
import {dataUrlToFile} from '@/lib/utils/mime';
import {PhotoEditor, type PhotoEditorInstance} from './PhotoEditor';

/**
 * Full screen photo editor on a file, saving its result as a new file of the
 * workspace through the integration `save` action.
 */
export function PhotoEditorDialog({
    open,
    onOpenChange,
    asset,
    file,
    integration,
    source,
    suggestedName,
    onSaved,
}: ModalProps<boolean> & {
    asset: Asset;
    /** The file the integration is attached to (the original) */
    file: ApiFile;
    integration: WorkspaceIntegration;
    /** The image opened in the editor: the original, or a previous export */
    source: {id: string; url: string};
    suggestedName?: string;
    onSaved?: () => void;
}) {
    const {t} = useTranslation();
    const editorRef = useRef<PhotoEditorInstance | null>(null);
    const [name, setName] = useState(suggestedName ?? '');
    const [saving, setSaving] = useState(false);
    const [progress, setProgress] = useState<number>();
    const canEdit = !!asset.capabilities.edit;

    const save = async () => {
        const dataUrl = editorRef.current?.toDataURL();
        if (!dataUrl) {
            toast.error(
                t('tui_photo_editor.not_ready', 'The editor is not ready yet')
            );

            return;
        }
        setSaving(true);
        try {
            const multipart = await multipartUpload(
                dataUrlToFile(dataUrl, `${name}.png`),
                {onProgress: p => setProgress(p.loaded / p.total)}
            );
            await runIntegrationAction(integration.id, 'save', {
                fileId: file.id,
                assetId: asset.id,
                name,
                multipart,
            });
            toast.success(t('tui_photo_editor.saved', 'Saved!'));
            onSaved?.();
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
            setProgress(undefined);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                size="full"
                className="gap-0 p-0"
                // The editor holds the pointer: closing is explicit
                onPointerDownOutside={e => e.preventDefault()}
            >
                <DialogHeader className="flex-row items-center gap-3 border-b px-4 py-3 pr-12">
                    <DialogTitle className="truncate">
                        {t('tui_photo_editor.title', 'Photo editor')}
                    </DialogTitle>
                    {canEdit ? (
                        <>
                            <Input
                                className="ml-auto w-64"
                                value={name}
                                onChange={e => setName(e.target.value)}
                                placeholder={t(
                                    'tui_photo_editor.file_name',
                                    'File name'
                                )}
                                disabled={saving}
                            />
                            <Button
                                onClick={save}
                                disabled={!name.trim()}
                                loading={saving}
                            >
                                <SaveIcon />{' '}
                                {t('tui_photo_editor.save_as', 'Save as')}
                            </Button>
                        </>
                    ) : null}
                </DialogHeader>
                {progress !== undefined ? (
                    <Progress
                        className="rounded-none"
                        value={Math.round(progress * 100)}
                    />
                ) : null}
                <DialogBody className="mx-0 overflow-hidden bg-media-bg px-0">
                    <PhotoEditor
                        key={source.id}
                        url={source.url}
                        name={asset.name ?? file.id}
                        editorRef={editorRef}
                    />
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}
