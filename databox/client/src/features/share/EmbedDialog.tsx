'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {RotateCcwIcon} from 'lucide-react';
import type {Asset, Share} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Button} from '@/components/ui/button';
import {Textarea} from '@/components/ui/input';
import {CopyButton} from '@/components/ui/copy-button';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {getShareUrl} from './ShareDialog';

export function EmbedDialog({
    open,
    onOpenChange,
    share,
    assets,
}: ModalProps & {share: Share; assets: Asset[]}) {
    const {t} = useTranslation();
    const defaultCode = useMemo(() => {
        const pageUrl = getShareUrl(share);
        // A single image is embedded as such, anything else as the share page
        const asset = assets.length === 1 ? assets[0] : undefined;
        const preview = share.alternateUrls?.find(
            a =>
                a.type?.startsWith('image/') &&
                (!a.assetId || a.assetId === asset?.id)
        );
        if (
            asset &&
            getFileKind(asset.source?.type) === FileKind.Image &&
            preview
        ) {
            return `<img src="${preview.url}" alt="${(asset.name ?? '').replace(/"/g, '&quot;')}" style="max-width:100%;height:auto" />`;
        }

        return `<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden">
  <iframe src="${pageUrl}?embed=1" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" allowfullscreen loading="lazy"></iframe>
</div>`;
    }, [share, assets]);
    const [code, setCode] = useState(defaultCode);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('share.embed', 'Embed code')}
            hideCancel
            submitLabel={t('common.close', 'Close')}
            bodyClassName="space-y-3"
        >
            <Textarea
                value={code}
                onChange={e => setCode(e.target.value)}
                className="min-h-32 font-mono text-xs"
                spellCheck={false}
            />
            <div className="flex gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setCode(defaultCode)}
                >
                    <RotateCcwIcon /> {t('common.reset', 'Reset')}
                </Button>
                <CopyButton
                    value={code}
                    size="sm"
                    variant="outline"
                    label={t('common.copy', 'Copy')}
                    className="px-3 text-foreground"
                />
            </div>
            <div>
                <p className="mb-1 text-xs font-semibold text-muted-foreground uppercase">
                    {t('share.embed_preview', 'Preview')}
                </p>
                <div
                    className="overflow-hidden rounded-md border bg-muted/30 p-2"
                    dangerouslySetInnerHTML={{__html: code}}
                />
            </div>
        </FormDialog>
    );
}
