'use client';

import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {
    LayoutTemplateIcon,
    ListIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {
    DropdownMenuCheckboxItem,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/menu';
import {useModals} from '@/components/modals/ModalProvider';
import {useGuardedRouter} from '@/components/modals/UnsavedChangesGuard';
import {usePreferencesStore} from '@/features/preferences/store';
import {useAuth} from '@/lib/auth/AuthProvider';
import {routes} from '@/lib/routes';
import {ProfileOwnership} from './ProfileOwnership';
import {useProfileStore} from './profileStore';
import {CreateProfileDialog, SelectProfileDialog} from './SelectProfileDialog';

/** Radio value of the default profile (no profile selected) */
const DEFAULT_PROFILE = '__default';

/**
 * The "Display profile" sub-menu of the settings menu: switch profile in one
 * click, keep the current one in sync with the preferences, and reach its
 * editor, a new profile or the whole list.
 */
export function ProfileMenu() {
    const {t} = useTranslation();
    const router = useGuardedRouter();
    const {openModal} = useModals();
    const {user} = useAuth();
    const profiles = useProfileStore(s => s.profiles);
    const current = useProfileStore(s => s.current);
    const next = useProfileStore(s => s.next);
    const load = useProfileStore(s => s.load);
    const loadMore = useProfileStore(s => s.loadMore);
    const setCurrent = useProfileStore(s => s.setCurrent);
    const syncPreferences = useProfileStore(s => s.syncPreferences);
    const isSynced = useProfileStore(s => s.isSynced);
    // Re-render on preference changes: `isSynced` compares them
    usePreferencesStore(s => s.preferences);
    const autoSync = usePreferencesStore(s => s.preferences.autoSync ?? false);
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const canEdit = !!current?.capabilities.edit;

    useEffect(() => {
        void load();
    }, [load]);

    const choose = (id: string) => {
        if (id === (current?.id ?? DEFAULT_PROFILE)) {
            return;
        }
        const profile =
            id === DEFAULT_PROFILE
                ? undefined
                : profiles.find(p => p.id === id);
        setCurrent(profile).catch((e: any) => toast.error(e?.message));
    };

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger data-testid="profile-menu">
                <LayoutTemplateIcon />
                <span className="flex min-w-0 flex-col">
                    <span className="text-xs text-muted-foreground">
                        {t('profile.display_profile', 'Display profile')}
                    </span>
                    <span className="truncate">
                        {current?.name ??
                            t('profile.default', 'Default display profile')}
                    </span>
                </span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="w-64">
                <DropdownMenuLabel>
                    {t('profile.menu.switch', 'Switch profile')}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={current?.id ?? DEFAULT_PROFILE}
                    onValueChange={choose}
                >
                    <DropdownMenuRadioItem
                        value={DEFAULT_PROFILE}
                        data-testid="profile-default"
                    >
                        {t('profile.default', 'Default display profile')}
                    </DropdownMenuRadioItem>
                    {profiles.map(p => (
                        <DropdownMenuRadioItem
                            key={p.id}
                            value={p.id}
                            data-testid={`profile-${p.id}`}
                            title={p.description || undefined}
                        >
                            <span className="min-w-0 flex-1 truncate">
                                {p.name}
                            </span>
                            <ProfileOwnership profile={p} userId={user?.id} />
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                {next ? (
                    <DropdownMenuItem
                        className="justify-center text-xs text-muted-foreground"
                        // Keep the menu open: the next page appears below
                        onSelect={e => {
                            e.preventDefault();
                            void loadMore();
                        }}
                    >
                        {t('common.load_more', 'Load more')}
                    </DropdownMenuItem>
                ) : null}
                {current && canEdit ? (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={autoSync}
                            data-testid="profile-auto-sync"
                            onSelect={e => e.preventDefault()}
                            onCheckedChange={v =>
                                void updatePreference('autoSync', v)
                            }
                        >
                            {t(
                                'profile.menu.auto_sync',
                                'Auto-sync preferences'
                            )}
                        </DropdownMenuCheckboxItem>
                        {!autoSync && !isSynced() ? (
                            <DropdownMenuItem
                                data-testid="profile-sync-now"
                                onSelect={async () => {
                                    try {
                                        await syncPreferences();
                                        toast.success(
                                            t(
                                                'profile.synced',
                                                'Profile updated'
                                            )
                                        );
                                    } catch (e: any) {
                                        toast.error(e?.message);
                                    }
                                }}
                            >
                                <RefreshCwIcon />
                                {t(
                                    'profile.sync_now',
                                    'Save current preferences to profile'
                                )}
                            </DropdownMenuItem>
                        ) : null}
                    </>
                ) : null}
                <DropdownMenuSeparator />
                {current && canEdit ? (
                    <DropdownMenuItem
                        data-testid="profile-edit-current"
                        onSelect={() =>
                            router.push(
                                routes.profileManage(current.id, 'attributes')
                            )
                        }
                    >
                        <PencilIcon />
                        <span className="truncate">
                            {t('profile.menu.edit', 'Edit “{{name}}”…', {
                                name: current.name,
                            })}
                        </span>
                    </DropdownMenuItem>
                ) : null}
                <DropdownMenuItem
                    data-testid="profile-create"
                    onSelect={() => openModal(CreateProfileDialog, {})}
                >
                    <PlusIcon /> {t('profile.menu.create', 'New profile…')}
                </DropdownMenuItem>
                <DropdownMenuItem
                    data-testid="profile-manage"
                    onSelect={() => openModal(SelectProfileDialog, {})}
                >
                    <ListIcon /> {t('profile.menu.manage', 'Manage profiles…')}
                </DropdownMenuItem>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
