'use client';

import {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import Link from 'next/link';
import {ExternalLinkIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {CopyButton} from '@/components/ui/copy-button';
import {
    CollectionChip,
    UserChip,
    WorkspaceChip,
    PrivacyChip,
} from '@/components/chips';
import {formatDateTime, formatFileSize} from '@/lib/utils/format';
import {routes} from '@/lib/routes';

export function InfoRow({
    label,
    children,
    copy,
}: {
    label: ReactNode;
    children: ReactNode;
    copy?: string;
}) {
    return (
        <div className="group/row flex flex-col gap-0.5 py-1.5 text-sm">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="flex min-w-0 items-center gap-1">
                <span className="min-w-0 flex-1 break-all">{children}</span>
                {copy ? (
                    <CopyButton
                        value={copy}
                        className="opacity-0 group-hover/row:opacity-100"
                    />
                ) : null}
            </dd>
        </div>
    );
}

export function AssetInfoList({asset}: {asset: Asset}) {
    const {t, i18n} = useTranslation();
    const dt = (v: string | undefined) =>
        v ? formatDateTime(v, 'medium', i18n.language) : '—';

    return (
        <dl className="divide-y">
            <InfoRow label="ID" copy={asset.id}>
                <code className="font-mono text-xs">{asset.id}</code>
            </InfoRow>
            {asset.owner ? (
                <InfoRow label={t('asset.info.owner', 'Owner')}>
                    <UserChip user={asset.owner} size="sm" />
                </InfoRow>
            ) : null}
            <InfoRow label={t('asset.info.created_at', 'Created at')}>
                {dt(asset.createdAt)}
            </InfoRow>
            <InfoRow label={t('asset.info.updated_at', 'Updated at')}>
                {dt(asset.updatedAt)}
            </InfoRow>
            <InfoRow label={t('asset.info.edited_at', 'Edited at')}>
                {dt(asset.editedAt)}
            </InfoRow>
            <InfoRow label={t('common.privacy', 'Privacy')}>
                <PrivacyChip privacy={asset.privacy} size="sm" />
            </InfoRow>
            <InfoRow label={t('asset.info.workspace', 'Workspace')}>
                <WorkspaceChip workspace={asset.workspace} size="sm" />
            </InfoRow>
            {asset.referenceCollection ? (
                <InfoRow
                    label={t(
                        'asset.info.reference_collection',
                        'Reference collection'
                    )}
                >
                    <CollectionChip
                        collection={asset.referenceCollection}
                        absolute
                        size="sm"
                    />
                </InfoRow>
            ) : null}
            {asset.source ? (
                <>
                    <InfoRow
                        label={t('asset.info.source_file', 'Source file')}
                        copy={asset.source.url}
                    >
                        <Link
                            href={routes.fileManage(asset.source.id, 'info')}
                            className="inline-flex items-center gap-1 text-primary hover:underline"
                        >
                            {asset.source.fileName ?? asset.source.id}
                        </Link>
                        {asset.source.url ? (
                            <a
                                href={asset.source.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="ml-1 inline-flex text-muted-foreground hover:text-foreground"
                            >
                                <ExternalLinkIcon className="size-3.5" />
                            </a>
                        ) : null}
                    </InfoRow>
                    <InfoRow label={t('asset.info.file_type', 'Type')}>
                        {asset.source.type} ·{' '}
                        {formatFileSize(asset.source.size, true, i18n.language)}
                    </InfoRow>
                </>
            ) : null}
        </dl>
    );
}
