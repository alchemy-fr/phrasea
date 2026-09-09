'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    InfoIcon,
    LayoutGridIcon,
    ListOrderedIcon,
    PencilIcon,
    SaveIcon,
    ShieldIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {DisplayProfile} from '@/types/api';
import {getProfile, putProfile} from '@/lib/api/misc';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import {InfoRow} from '@/features/assets/view/AssetInfoList';
import {UserChip} from '@/components/chips';
import {Badge} from '@/components/ui/misc';
import {formatDateTime} from '@/lib/utils/format';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {AclEditor} from '@/features/permissions/AclEditor';
import {genericPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {useProfileStore} from '../profileStore';
import {OrganizeProfileTab} from './OrganizeProfileTab';
import {GridProfileEditorTab} from './GridProfileEditorTab';

export type ProfileTabProps = {profile: DisplayProfile; refresh: () => void};

export function ProfileManageRoute({
    profileId,
    tab,
}: {
    profileId: string;
    tab: string;
}) {
    const {t} = useTranslation();
    const upsert = useProfileStore(s => s.upsert);
    const query = useQuery({
        queryKey: ['profile', profileId],
        queryFn: async () => {
            const p = await getProfile(profileId);
            upsert(p);

            return p;
        },
    });
    const profile = query.data;
    if (!profile) {
        return (
            <RouteDialog size="lg">
                <FullPageLoader />
            </RouteDialog>
        );
    }

    const tabs: DialogTab<ProfileTabProps>[] = [
        {
            id: 'info',
            title: t('collection.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: InfoTab,
        },
        {
            id: 'organize',
            title: t('profile.organize', 'Organize'),
            icon: <ListOrderedIcon />,
            component: OrganizeProfileTab,
            enabled: profile.capabilities.edit,
        },
        {
            id: 'grid',
            title: t('profile.grid_card', 'Grid card'),
            icon: <LayoutGridIcon />,
            component: GridProfileEditorTab,
            enabled: profile.capabilities.edit,
        },
        {
            id: 'edit',
            title: t('common.edit', 'Edit'),
            icon: <PencilIcon />,
            component: EditTab,
            enabled: profile.capabilities.edit,
        },
        {
            id: 'permissions',
            title: t('collection.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: PermissionsTab,
            enabled: profile.capabilities.editPermissions,
        },
    ];

    return (
        <TabbedRouteDialog<ProfileTabProps>
            title={t('profile.manage.title', 'Display profile')}
            subtitle={profile.name}
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.profileManage(profileId, next)}
            baseProps={{profile, refresh: () => query.refetch()}}
            size="xl"
        />
    );
}

function InfoTab({profile}: ProfileTabProps) {
    const {t, i18n} = useTranslation();

    return (
        <dl className="max-w-2xl divide-y">
            <InfoRow label={t('common.name', 'Name')}>{profile.name}</InfoRow>
            {profile.description ? (
                <InfoRow label={t('common.description', 'Description')}>
                    {profile.description}
                </InfoRow>
            ) : null}
            {profile.owner ? (
                <InfoRow label={t('asset.info.owner', 'Owner')}>
                    <UserChip user={profile.owner} size="sm" />
                </InfoRow>
            ) : null}
            <InfoRow label={t('common.visibility', 'Visibility')}>
                <Badge variant={profile.public ? 'success' : 'muted'}>
                    {profile.public
                        ? t('common.public', 'Public')
                        : t('common.private', 'Private')}
                </Badge>
            </InfoRow>
            <InfoRow label={t('asset.info.created_at', 'Created at')}>
                {formatDateTime(profile.createdAt, 'medium', i18n.language)}
            </InfoRow>
            <InfoRow label={t('asset.info.updated_at', 'Updated at')}>
                {formatDateTime(profile.updatedAt, 'medium', i18n.language)}
            </InfoRow>
        </dl>
    );
}

function EditTab({profile, refresh}: ProfileTabProps) {
    const {t} = useTranslation();
    const upsert = useProfileStore(s => s.upsert);
    const [name, setName] = useState(profile.name);
    const [description, setDescription] = useState(profile.description ?? '');
    const [isPublic, setIsPublic] = useState(!!profile.public);
    const [exclusive, setExclusive] = useState(!!profile.exclusive);
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const saved = await putProfile(profile.id, {
                name,
                description,
                public: isPublic,
                exclusive,
            });
            upsert(saved);
            refresh();
            toast.success(t('profile.saved', 'Profile saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="max-w-xl space-y-3">
            <FormRow label={t('common.name', 'Name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            <FormRow label={t('common.description', 'Description')}>
                <Textarea
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                />
            </FormRow>
            <LabeledControl label={t('common.public', 'Public')}>
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
            <LabeledControl
                label={t('profile.exclusive', 'Exclusive')}
                description={t(
                    'profile.exclusive_help',
                    'Only show the pinned attributes, hide all the others.'
                )}
            >
                <Switch checked={exclusive} onCheckedChange={setExclusive} />
            </LabeledControl>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function PermissionsTab({profile}: ProfileTabProps) {
    const {t} = useTranslation();

    return (
        <AclEditor
            objectType={PermissionObject.Profile}
            objectId={profile.id}
            definitions={genericPermissions(t)}
        />
    );
}
