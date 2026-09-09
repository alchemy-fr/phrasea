'use client';

import {useTranslation} from 'react-i18next';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {InfoRow} from '@/features/assets/view/AssetInfoList';
import {UserChip} from '@/components/chips';
import {Badge} from '@/components/ui/misc';
import {formatDateTime} from '@/lib/utils/format';

export function WorkspaceInfoTab({workspace}: WorkspaceTabProps) {
    const {t, i18n} = useTranslation();

    return (
        <dl className="max-w-2xl divide-y">
            <InfoRow label="ID" copy={workspace.id}>
                <code className="font-mono text-xs">{workspace.id}</code>
            </InfoRow>
            {workspace.slug ? (
                <InfoRow label={t('workspace.slug', 'Slug')}>
                    {workspace.slug}
                </InfoRow>
            ) : null}
            {workspace.owner ? (
                <InfoRow label={t('asset.info.owner', 'Owner')}>
                    <UserChip user={workspace.owner} size="sm" />
                </InfoRow>
            ) : null}
            <InfoRow label={t('asset.info.created_at', 'Created at')}>
                {formatDateTime(workspace.createdAt, 'medium', i18n.language)}
            </InfoRow>
            <InfoRow label={t('common.public', 'Public')}>
                <Badge variant={workspace.public ? 'success' : 'muted'}>
                    {workspace.public
                        ? t('common.yes', 'Yes')
                        : t('common.no', 'No')}
                </Badge>
            </InfoRow>
            <InfoRow label={t('workspace.locales', 'Locales')}>
                {workspace.enabledLocales?.join(', ') || '—'}
            </InfoRow>
        </dl>
    );
}
