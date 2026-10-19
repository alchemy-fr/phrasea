'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Progress} from '@/components/ui/misc';
import {FileOrUrl, FileOrUrlInput} from '@/components/form/FileOrUrlInput';
import {multipartUpload} from '@/lib/api/upload';
import {patchAsset} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {useConfig} from '@/lib/config/ConfigProvider';
import {toDropzoneAccept} from '@/lib/utils/mime';
import {AssetEvent, EventCategory, track} from '@/lib/analytics/events';

export function ReplaceSourceDialog({
    open,
    onOpenChange,
    resolve,
    asset,
    onComplete,
}: ModalProps<Asset> & {asset: Asset; onComplete?: () => void}) {
    const {t} = useTranslation();
    const config = useConfig();
    const [value, setValue] = useState<FileOrUrl>({});
    const [progress, setProgress] = useState<number>();
    const update = useAssetStore(s => s.update);
    // An asset created without any file gets its first source here
    const adding = !asset.source;

    const submit = async () => {
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
            track(EventCategory.Asset, AssetEvent.ReplaceSource, {
                name: value.file ? 'upload' : 'url',
            });
            toast.success(
                adding
                    ? t('asset.replace.added', 'Source file added')
                    : t('asset.replace.done', 'Source file replaced')
            );
            onComplete?.();
            resolve?.(updated);
        } finally {
            setProgress(undefined);
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                adding
                    ? t('asset.replace.add_title', 'Add a source file')
                    : t('asset.replace.title', 'Replace source file')
            }
            description={
                adding
                    ? t(
                          'asset.replace.add_help',
                          'Renditions will be generated from this file.'
                      )
                    : t(
                          'asset.replace.help',
                          'The current file is kept as a version. Renditions will be regenerated.'
                      )
            }
            submitLabel={
                adding
                    ? t('asset.replace.add_submit', 'Add')
                    : t('asset.replace.submit', 'Replace')
            }
            canSubmit={!!value.file || !!value.url}
            dirty={!!value.file || !!value.url}
            bodyClassName="space-y-3"
            onSubmit={submit}
        >
            <FileOrUrlInput
                value={value}
                onChange={setValue}
                accept={toDropzoneAccept(config.upload.allowedTypes)}
            />
            {progress !== undefined ? (
                <Progress value={Math.round(progress * 100)} />
            ) : null}
        </FormDialog>
    );
}
