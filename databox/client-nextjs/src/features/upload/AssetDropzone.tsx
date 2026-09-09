'use client';

import {PropsWithChildren, useEffect} from 'react';
import {useDropzone} from 'react-dropzone';
import {useTranslation} from 'react-i18next';
import {UploadCloudIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';
import {toDropzoneAccept} from '@/lib/utils/mime';
import {useConfig} from '@/lib/config/ConfigProvider';

type Props = PropsWithChildren<{
    onDrop?: (files: File[]) => void;
    className?: string;
}>;

/**
 * Whole-page drop target for assets. Also handles pasting an image from the
 * clipboard (Ctrl+V).
 */
export function AssetDropzone({onDrop, className, children}: Props) {
    const {t} = useTranslation();
    const config = useConfig();
    const {getRootProps, isDragActive} = useDropzone({
        onDrop: files => {
            if (files.length > 0) {
                onDrop?.(files);
            }
        },
        noClick: true,
        noKeyboard: true,
        disabled: !onDrop,
        accept: toDropzoneAccept(config.upload.allowedTypes),
    });

    useEffect(() => {
        if (!onDrop) {
            return;
        }
        const onPaste = (e: ClipboardEvent) => {
            const target = e.target as HTMLElement | null;
            if (target && ['INPUT', 'TEXTAREA'].includes(target.tagName)) {
                return;
            }
            const files = Array.from(e.clipboardData?.items ?? [])
                .filter(i => i.kind === 'file')
                .map(i => i.getAsFile())
                .filter((f): f is File => !!f);
            if (files.length > 0) {
                e.preventDefault();
                onDrop(files);
            }
        };
        window.addEventListener('paste', onPaste);

        return () => window.removeEventListener('paste', onPaste);
    }, [onDrop]);

    return (
        <div {...getRootProps({className: cn('relative', className)})}>
            {children}
            {isDragActive ? (
                <div className="pointer-events-none absolute inset-0 z-40 flex items-center justify-center bg-primary/10 backdrop-blur-[1px]">
                    <div className="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-primary bg-background/90 px-10 py-8 text-primary shadow-xl">
                        <UploadCloudIcon className="size-10" />
                        <div className="font-medium">
                            {t('upload.drop_here', 'Drop files to upload')}
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
