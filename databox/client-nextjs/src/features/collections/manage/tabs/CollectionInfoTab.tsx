'use client';

import {useTranslation} from 'react-i18next';
import Link from 'next/link';
import type {CollectionTabProps} from '../CollectionManageRoute';
import {InfoRow} from '@/features/assets/view/AssetInfoList';
import {UserChip, WorkspaceChip, PrivacyChip} from '@/components/chips';
import {formatDateTime} from '@/lib/utils/format';
import {routes} from '@/lib/routes';

export function CollectionInfoTab({collection}: CollectionTabProps) {
    const {t, i18n} = useTranslation();

    return (
        <dl className="max-w-2xl divide-y">
            <InfoRow label="ID" copy={collection.id}>
                <code className="font-mono text-xs">{collection.id}</code>
            </InfoRow>
            {collection.owner ? (
                <InfoRow label={t('asset.info.owner', 'Owner')}>
                    <UserChip user={collection.owner} size="sm" />
                </InfoRow>
            ) : null}
            <InfoRow label={t('asset.info.created_at', 'Created at')}>
                {formatDateTime(collection.createdAt, 'medium', i18n.language)}
            </InfoRow>
            <InfoRow label={t('asset.info.updated_at', 'Updated at')}>
                {formatDateTime(collection.updatedAt, 'medium', i18n.language)}
            </InfoRow>
            <InfoRow label={t('common.privacy', 'Privacy')}>
                <PrivacyChip privacy={collection.privacy} size="sm" />
            </InfoRow>
            <InfoRow label={t('asset.info.workspace', 'Workspace')}>
                <Link
                    href={routes.workspaceManage(
                        collection.workspace.id,
                        'info'
                    )}
                >
                    <WorkspaceChip workspace={collection.workspace} size="sm" />
                </Link>
            </InfoRow>
            <InfoRow
                label={t('collection.info.path', 'Path')}
                copy={collection.absoluteDisplayName}
            >
                {collection.absoluteDisplayName ?? collection.absoluteName}
            </InfoRow>
        </dl>
    );
}
