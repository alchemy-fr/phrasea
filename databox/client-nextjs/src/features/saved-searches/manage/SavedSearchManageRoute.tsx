'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    InfoIcon,
    PencilIcon,
    SaveIcon,
    ShieldIcon,
    ZapIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {SavedSearch, SavedSearchPrivacy} from '@/types/api';
import {getSavedSearch, putSavedSearch} from '@/lib/api/misc';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import {InfoRow} from '@/features/assets/view/AssetInfoList';
import {UserChip} from '@/components/chips';
import {formatDateTime} from '@/lib/utils/format';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {EmptyState} from '@/components/ui/misc';
import {SavedSearchPrivacyField} from '../SavedSearchPrivacyField';
import {AclEditor} from '@/features/permissions/AclEditor';
import {genericPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';

type TabProps = {savedSearch: SavedSearch; refresh: () => void};

export function SavedSearchManageRoute({
    savedSearchId,
    tab,
}: {
    savedSearchId: string;
    tab: string;
}) {
    const {t} = useTranslation();
    const query = useQuery({
        queryKey: ['saved-search', savedSearchId],
        queryFn: () => getSavedSearch(savedSearchId),
    });
    const saved = query.data;
    if (!saved) {
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
            id: 'edit',
            title: t('common.edit', 'Edit'),
            icon: <PencilIcon />,
            component: EditTab,
            enabled: saved.capabilities.edit,
        },
        {
            id: 'permissions',
            title: t('collection.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: PermissionsTab,
            enabled: saved.capabilities.editPermissions,
        },
        {
            id: 'automations',
            title: t('saved_search.automations', 'Automations'),
            icon: <ZapIcon />,
            component: AutomationsTab,
        },
    ];

    return (
        <TabbedRouteDialog<TabProps>
            title={t('saved_search.manage.title', 'Manage saved search')}
            subtitle={saved.name}
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.savedSearchManage(savedSearchId, next)}
            baseProps={{savedSearch: saved, refresh: () => query.refetch()}}
            size="md"
        />
    );
}

function InfoTab({savedSearch}: TabProps) {
    const {t, i18n} = useTranslation();

    return (
        <dl className="divide-y">
            <InfoRow label="ID" copy={savedSearch.id}>
                <code className="font-mono text-xs">{savedSearch.id}</code>
            </InfoRow>
            {savedSearch.owner ? (
                <InfoRow label={t('asset.info.owner', 'Owner')}>
                    <UserChip user={savedSearch.owner} size="sm" />
                </InfoRow>
            ) : null}
            <InfoRow label={t('asset.info.created_at', 'Created at')}>
                {formatDateTime(savedSearch.createdAt, 'medium', i18n.language)}
            </InfoRow>
            <InfoRow label={t('saved_search.query', 'Query')}>
                {savedSearch.data.query || '—'}
            </InfoRow>
            <InfoRow label={t('saved_search.conditions', 'Conditions')}>
                <ul className="space-y-1 font-mono text-xs">
                    {(savedSearch.data.conditions ?? []).map(c => (
                        <li key={c.id}>{c.query}</li>
                    ))}
                    {(savedSearch.data.conditions ?? []).length === 0
                        ? '—'
                        : null}
                </ul>
            </InfoRow>
        </dl>
    );
}

function EditTab({savedSearch, refresh}: TabProps) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [name, setName] = useState(savedSearch.name);
    const [privacy, setPrivacy] = useState<SavedSearchPrivacy>(
        savedSearch.privacy ?? 0
    );
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            await putSavedSearch(savedSearch.id, {name, privacy});
            refresh();
            void queryClient.invalidateQueries({queryKey: ['saved-searches']});
            toast.success(t('saved_search.updated', 'Search updated'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3">
            <FormRow label={t('common.name', 'Name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            <SavedSearchPrivacyField value={privacy} onChange={setPrivacy} />
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function PermissionsTab({savedSearch}: TabProps) {
    const {t} = useTranslation();

    return (
        <AclEditor
            objectType={PermissionObject.SavedSearch}
            objectId={savedSearch.id}
            definitions={genericPermissions(t)}
        />
    );
}

function AutomationsTab() {
    const {t} = useTranslation();

    return (
        <EmptyState
            icon={<ZapIcon />}
            title={t(
                'saved_search.automations_soon',
                'Automations are coming soon'
            )}
        />
    );
}
