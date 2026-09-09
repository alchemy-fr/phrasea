'use client';

import {useTranslation} from 'react-i18next';
import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {InfoIcon, ListTreeIcon, RefreshCwIcon} from 'lucide-react';
import type {ApiFile} from '@/types/api';
import {getFile, getFileMetadata} from '@/lib/api/misc';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader, InlineLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import {InfoRow} from '@/features/assets/view/AssetInfoList';
import {formatFileSize} from '@/lib/utils/format';
import {Badge} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {CopyButton} from '@/components/ui/copy-button';
import {AnalysisChip} from '@/features/assets/quarantine/AnalysisChip';
import {severityLabels} from '@/features/assets/quarantine/QuarantineBanner';

type TabProps = {file: ApiFile};

export function FileManageRoute({fileId, tab}: {fileId: string; tab: string}) {
    const {t} = useTranslation();
    const query = useQuery({
        queryKey: ['file', fileId],
        queryFn: () => getFile(fileId),
    });
    const file = query.data;
    if (!file) {
        return (
            <RouteDialog size="md">
                <FullPageLoader />
            </RouteDialog>
        );
    }

    const tabs: DialogTab<TabProps>[] = [
        {
            id: 'info',
            title: t('collection.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: InfoTab,
        },
        {
            id: 'metadata',
            title: t('file.metadata', 'Metadata'),
            icon: <ListTreeIcon />,
            component: MetadataTab,
        },
    ];

    return (
        <TabbedRouteDialog<TabProps>
            title={t('file.manage.title', 'File')}
            subtitle={file.fileName}
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.fileManage(fileId, next)}
            baseProps={{file}}
            size="md"
        />
    );
}

function InfoTab({file}: TabProps) {
    const {t, i18n} = useTranslation();
    const analysis = (file.analysis ?? {}) as Record<string, any>;
    const analyzers = Object.entries(analysis).filter(
        ([k]) => !['accepted', 'status', 'reason'].includes(k)
    );

    return (
        <dl className="divide-y">
            <InfoRow label="ID" copy={file.id}>
                <code className="font-mono text-xs">{file.id}</code>
            </InfoRow>
            {file.url ? (
                <InfoRow label="URL" copy={file.url}>
                    <a
                        href={file.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="break-all text-primary hover:underline"
                    >
                        {file.url}
                    </a>
                </InfoRow>
            ) : null}
            <InfoRow label={t('file.type', 'MIME type')}>{file.type}</InfoRow>
            <InfoRow label={t('file.size', 'Size')}>
                {formatFileSize(file.size, true, i18n.language)}
            </InfoRow>
            <InfoRow
                label={t('file.checksum', 'Checksum')}
                copy={file.checksum}
            >
                <code className="font-mono text-xs">{file.checksum}</code>
            </InfoRow>
            {file.docUniqueId ? (
                <InfoRow
                    label={t('file.doc_unique_id', 'Document unique ID')}
                    copy={file.docUniqueId}
                >
                    <code className="font-mono text-xs">
                        {file.docUniqueId}
                    </code>
                </InfoRow>
            ) : null}
            <InfoRow label={t('file.analysis', 'Analysis')}>
                <div className="space-y-1">
                    <AnalysisChip file={file} />
                    {!file.analysisPending && file.accepted !== false ? (
                        <Badge variant="success">
                            {t('file.analysis.ok', 'Accepted')}
                        </Badge>
                    ) : null}
                    {analyzers.map(([name, r]) => (
                        <div
                            key={name}
                            className="flex items-center gap-2 text-xs"
                        >
                            <Badge
                                variant={
                                    r?.passed === false
                                        ? 'destructive'
                                        : 'muted'
                                }
                            >
                                {name}
                            </Badge>
                            {typeof r?.level === 'number' ? (
                                <span className="uppercase text-muted-foreground">
                                    {severityLabels[r.level]}
                                </span>
                            ) : null}
                            <span>{r?.message ?? ''}</span>
                        </div>
                    ))}
                </div>
            </InfoRow>
            {file.usages && file.usages.length > 0 ? (
                <InfoRow label={t('file.referenced_by', 'Referenced by')}>
                    <ul className="space-y-1 text-sm">
                        {file.usages.map((u, i) => (
                            <li key={i} className="flex items-center gap-2">
                                <Badge variant="muted">{u.type}</Badge>
                                <Link
                                    href={routes.assetView(u.assetId)}
                                    className="text-primary hover:underline"
                                >
                                    {u.assetTitle ?? u.assetId}
                                </Link>
                                {u.name ? (
                                    <span className="text-xs text-muted-foreground">
                                        ({u.name})
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </InfoRow>
            ) : null}
        </dl>
    );
}

function MetadataTab({file}: TabProps) {
    const {t} = useTranslation();
    const metadata = useQuery({
        queryKey: ['file-metadata', file.id],
        queryFn: () => getFileMetadata(file.id),
    });
    const json = metadata.data
        ? JSON.stringify(metadata.data.metadata ?? metadata.data, null, 2)
        : '';

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-end gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => metadata.refetch()}
                >
                    <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                </Button>
                {json ? <CopyButton value={json} /> : null}
            </div>
            {metadata.isLoading ? (
                <InlineLoader />
            ) : (
                <pre className="max-h-[60vh] overflow-auto rounded-md bg-muted p-3 font-mono text-xs">
                    {json}
                </pre>
            )}
        </div>
    );
}
