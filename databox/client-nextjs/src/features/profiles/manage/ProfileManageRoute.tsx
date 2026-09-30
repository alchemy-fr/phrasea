'use client';

import {useState, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    InfoIcon,
    LayoutGridIcon,
    ListOrderedIcon,
    SaveIcon,
    ShieldIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {DisplayProfile} from '@/types/api';
import {getProfile, putProfile} from '@/lib/api/misc';
import {
    DialogTab,
    TabbedRouteDialogShell,
} from '@/components/modals/TabbedRouteDialog';
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
import {ProfileAttributesTab} from './ProfileAttributesTab';
import {ProfileGridTab} from './ProfileGridTab';
import {profileQueryKey} from './useProfileEditing';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

export type ProfileTabProps = {profile: DisplayProfile; refresh: () => void};

/** Shared by the shell and the tab content: one query, one request. */
function useProfile(profileId: string) {
    const upsert = useProfileStore(s => s.upsert);

    return useQuery({
        queryKey: profileQueryKey(profileId),
        queryFn: async () => {
            const p = await getProfile(profileId);
            upsert(p);

            return p;
        },
    });
}

function useTabs(profile?: DisplayProfile): DialogTab<ProfileTabProps>[] {
    const {t} = useTranslation();
    const canEdit = !!profile?.capabilities.edit;

    return [
        {
            id: 'general',
            title: t('profile.manage.general', 'General'),
            icon: <InfoIcon />,
            component: GeneralTab,
        },
        {
            id: 'attributes',
            title: t('profile.manage.attributes', 'Asset attributes'),
            icon: <ListOrderedIcon />,
            component: ProfileAttributesTab,
            enabled: canEdit,
        },
        {
            id: 'grid',
            title: t('profile.grid_card', 'Grid card'),
            icon: <LayoutGridIcon />,
            component: ProfileGridTab,
            enabled: canEdit,
        },
        {
            id: 'permissions',
            title: t('collection.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: PermissionsTab,
            enabled: !!profile?.capabilities.editPermissions,
        },
    ];
}

export function ProfileManageShell({profileId}: {profileId: string}) {
    const {t} = useTranslation();
    const query = useProfile(profileId);
    const profile = query.data;
    const tabs = useTabs(profile);

    // Stable: every tab kept mounted receives it, and is memoized (`refetch`
    // is stable, the query result object is not)
    const {refetch} = query;
    const baseProps = useMemo(
        () => (profile ? {profile, refresh: () => void refetch()} : undefined),
        [profile, refetch]
    );

    return (
        <TabbedRouteDialogShell
            title={t('profile.manage.title', 'Display profile')}
            subtitle={profile?.name}
            tabs={tabs}
            baseProps={baseProps}
            buildTabHref={tab => routes.profileManage(profileId, tab)}
            size="xl"
            placeholder={profile ? undefined : <FullPageLoader />}
        />
    );
}

/** The profile settings (read-only without the edit right) and its facts */
function GeneralTab(props: ProfileTabProps) {
    const {profile} = props;
    const {t, i18n} = useTranslation();

    return (
        <div className="grid max-w-4xl gap-8 md:grid-cols-[3fr_2fr]">
            {profile.capabilities.edit ? (
                <SettingsForm {...props} />
            ) : (
                <dl className="divide-y">
                    <InfoRow label={t('common.name', 'Name')}>
                        {profile.name}
                    </InfoRow>
                    {profile.description ? (
                        <InfoRow label={t('common.description', 'Description')}>
                            {profile.description}
                        </InfoRow>
                    ) : null}
                </dl>
            )}
            <dl className="divide-y self-start rounded-lg border bg-muted/30 px-3 text-sm">
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
        </div>
    );
}

function SettingsForm({profile, refresh}: ProfileTabProps) {
    const {t} = useTranslation();
    const upsert = useProfileStore(s => s.upsert);
    const [name, setName] = useState(profile.name);
    const [description, setDescription] = useState(profile.description ?? '');
    const [isPublic, setIsPublic] = useState(!!profile.public);
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState({name, description, isPublic});

    const save = async () => {
        setSaving(true);
        try {
            const saved = await putProfile(profile.id, {
                name,
                description,
                public: isPublic,
            });
            upsert(saved);
            markSaved();
            refresh();
            toast.success(t('profile.saved', 'Profile saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <form
            className="space-y-3"
            onSubmit={e => {
                e.preventDefault();
                void save();
            }}
        >
            <FormRow label={t('common.name', 'Name')} htmlFor="profile-name">
                <Input
                    id="profile-name"
                    value={name}
                    onChange={e => setName(e.target.value)}
                />
            </FormRow>
            <FormRow
                label={t('common.description', 'Description')}
                htmlFor="profile-description"
            >
                <Textarea
                    id="profile-description"
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                />
            </FormRow>
            <LabeledControl
                label={t('common.public', 'Public')}
                description={t(
                    'profile.public_help',
                    'Every user can select a public profile.'
                )}
            >
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
            <div className="flex justify-end">
                <Button type="submit" loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </form>
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
