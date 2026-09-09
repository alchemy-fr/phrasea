'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset, RenditionDefinition} from '@/types/api';
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
import {Progress} from '@/components/ui/misc';
import {FileOrUrl, FileOrUrlInput} from '@/components/form/FileOrUrlInput';
import {multipartUpload} from '@/lib/api/upload';
import {postRendition} from '@/lib/api/misc';

export function UploadRenditionDialog({
    open,
    onOpenChange,
    asset,
    definition,
    onUploaded,
}: ModalProps & {
    asset: Asset;
    definition: RenditionDefinition | {id: string};
    onUploaded?: () => void;
}) {
    const {t} = useTranslation();
    const [value, setValue] = useState<FileOrUrl>({});
    const [progress, setProgress] = useState<number>();
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        setLoading(true);
        try {
            if (value.file) {
                const multipart = await multipartUpload(value.file, {
                    onProgress: p => setProgress(p.loaded / p.total),
                });
                await postRendition({
                    assetId: asset.id,
                    definitionId: definition.id,
                    multipart,
                    substituted: true,
                });
            } else {
                await postRendition({
                    assetId: asset.id,
                    definitionId: definition.id,
                    sourceFile: {url: value.url, importFile: true},
                    substituted: true,
                });
            }
            toast.success(t('rendition.uploaded', 'Rendition uploaded'));
            onUploaded?.();
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
                        {t(
                            'rendition.upload_title',
                            'Upload rendition "{{name}}"',
                            {
                                name:
                                    'displayName' in definition
                                        ? (definition.displayName ??
                                          definition.name)
                                        : '',
                            }
                        )}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-3">
                    <FileOrUrlInput value={value} onChange={setValue} />
                    {progress !== undefined ? (
                        <Progress value={Math.round(progress * 100)} />
                    ) : null}
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={loading}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!value.file && !value.url}
                        loading={loading}
                    >
                        {t('rendition.upload', 'Upload')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
