'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset, RenditionDefinition} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Progress} from '@/components/ui/misc';
import {FileOrUrl, FileOrUrlInput} from '@/components/form/FileOrUrlInput';
import {multipartUpload} from '@/lib/api/upload';
import {postRendition} from '@/lib/api/misc';

export function UploadRenditionDialog({
    open,
    onOpenChange,
    resolve,
    asset,
    definition,
    onUploaded,
}: ModalProps<boolean> & {
    asset: Asset;
    definition: RenditionDefinition | {id: string};
    onUploaded?: () => void;
}) {
    const {t} = useTranslation();
    const [value, setValue] = useState<FileOrUrl>({});
    const [progress, setProgress] = useState<number>();

    const submit = async () => {
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
            resolve?.(true);
        } finally {
            setProgress(undefined);
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('rendition.upload_title', 'Upload rendition "{{name}}"', {
                name:
                    'displayName' in definition
                        ? (definition.displayName ?? definition.name)
                        : '',
            })}
            submitLabel={t('rendition.upload', 'Upload')}
            canSubmit={!!value.file || !!value.url}
            dirty={!!value.file || !!value.url}
            bodyClassName="space-y-3"
            onSubmit={submit}
        >
            <FileOrUrlInput value={value} onChange={setValue} />
            {progress !== undefined ? (
                <Progress value={Math.round(progress * 100)} />
            ) : null}
        </FormDialog>
    );
}
