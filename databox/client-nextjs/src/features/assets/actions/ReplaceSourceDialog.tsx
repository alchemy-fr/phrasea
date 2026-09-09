'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Progress} from '@/components/ui/misc';
import {FileOrUrl, FileOrUrlInput} from '@/components/form/FileOrUrlInput';
import {multipartUpload} from '@/lib/api/upload';
import {patchAsset} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {useConfig} from '@/lib/config/ConfigProvider';
import {toDropzoneAccept} from '@/lib/utils/mime';

export function ReplaceSourceDialog({
    open,
    onOpenChange,
    asset,
    onComplete,
}: ModalProps & {asset: Asset; onComplete?: () => void}) {
    const {t} = useTranslation();
    const config = useConfig();
    const [value, setValue] = useState<FileOrUrl>({});
    const [progress, setProgress] = useState<number>();
    const [loading, setLoading] = useState(false);
    const update = useAssetStore(s => s.update);

    const submit = async () => {
        setLoading(true);
        try {
            let updated: Asset;
            if (value.file) {
                const multipart = await multipartUpload(value.file, {
                    onProgress: p => setProgress(p.loaded / p.total),
                });
                updated = await patchAsset(asset.id, {multipart});
            } else {
                updated = await patchAsset(asset.id, {
                    sourceFile: {url: value.url, importFile: true},
                });
            }
            update(updated);
            toast.success(t('asset.replace.done', 'Source file replaced'));
            onComplete?.();
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
            setProgress(undefined);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('asset.replace.title', 'Replace source file')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'asset.replace.help',
                            'The current file is kept as a version. Renditions will be regenerated.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody className="space-y-3">
                    <FileOrUrlInput
                        value={value}
                        onChange={setValue}
                        accept={toDropzoneAccept(config.upload.allowedTypes)}
                    />
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
                        {t('asset.replace.submit', 'Replace')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
