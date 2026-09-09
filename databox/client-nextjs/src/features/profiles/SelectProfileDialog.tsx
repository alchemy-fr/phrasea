'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    CheckIcon,
    GlobeIcon,
    MoreVerticalIcon,
    PlusIcon,
    RefreshCwIcon,
    UserIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Input, FormRow, Textarea} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useProfileStore} from './profileStore';
import {usePreferencesStore} from '@/features/preferences/store';
import {postProfile} from '@/lib/api/misc';
import {routes} from '@/lib/routes';
import type {DisplayProfile} from '@/types/api';
import {useAuth} from '@/lib/auth/AuthProvider';
import {cn} from '@/lib/utils/cn';

export function SelectProfileDialog({open, onOpenChange}: ModalProps) {
    const {t} = useTranslation();
    const {user} = useAuth();
    const router = useRouter();
    const {openModal} = useModals();
    const {
        profiles,
        current,
        setCurrent,
        remove,
        syncPreferences,
        isSynced,
        next,
        loadMore,
    } = useProfileStore();
    const autoSync = usePreferencesStore(s => s.preferences.autoSync ?? false);
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const synced = isSynced();

    const choose = async (profile: DisplayProfile | undefined) => {
        await setCurrent(profile);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('profile.select.title', 'Display profile')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-2">
                    <ProfileRow
                        profile={undefined}
                        current={current}
                        userId={user?.id}
                        onChoose={choose}
                        onDelete={remove}
                        onEdit={id => {
                            onOpenChange(false);
                            router.push(routes.profileManage(id, 'organize'));
                        }}
                    />
                    {profiles.map(p => (
                        <ProfileRow
                            key={p.id}
                            profile={p}
                            current={current}
                            userId={user?.id}
                            onChoose={choose}
                            onDelete={remove}
                            onEdit={id => {
                                onOpenChange(false);
                                router.push(
                                    routes.profileManage(id, 'organize')
                                );
                            }}
                        />
                    ))}
                    {next ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-full"
                            onClick={() => loadMore()}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    ) : null}
                    {current ? (
                        <div className="mt-4 space-y-2 rounded-md bg-muted/50 p-3">
                            <LabeledControl
                                label={t(
                                    'profile.auto_sync',
                                    'Auto-sync preferences to this profile'
                                )}
                                description={t(
                                    'profile.auto_sync_help',
                                    'Layout, thumbnail size, facets and theme changes are saved to the profile.'
                                )}
                            >
                                <Switch
                                    checked={autoSync}
                                    disabled={!current.capabilities.edit}
                                    onCheckedChange={v =>
                                        updatePreference('autoSync', v)
                                    }
                                />
                            </LabeledControl>
                            {!synced && current.capabilities.edit ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={async () => {
                                        await syncPreferences();
                                        toast.success(
                                            t(
                                                'profile.synced',
                                                'Profile updated'
                                            )
                                        );
                                    }}
                                >
                                    <RefreshCwIcon />{' '}
                                    {t(
                                        'profile.sync_now',
                                        'Save current preferences to profile'
                                    )}
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </DialogBody>
                <DialogFooter className="sm:justify-between">
                    <Button
                        variant="outline"
                        onClick={() =>
                            openModal(CreateProfileDialog, {
                                onCreated: () => onOpenChange(false),
                            })
                        }
                    >
                        <PlusIcon /> {t('profile.create', 'New profile')}
                    </Button>
                    <Button onClick={() => onOpenChange(false)}>
                        {t('common.close', 'Close')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ProfileRow({
    profile,
    current,
    userId,
    onChoose,
    onDelete,
    onEdit,
}: {
    profile: DisplayProfile | undefined;
    current: DisplayProfile | undefined;
    userId: string | undefined;
    onChoose: (profile: DisplayProfile | undefined) => void;
    onDelete: (id: string) => Promise<void>;
    onEdit: (id: string) => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const active = (profile?.id ?? null) === (current?.id ?? null);
    const shared = profile && profile.owner && profile.owner.id !== userId;

    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
                active && 'border-primary bg-primary/5'
            )}
        >
            <button
                type="button"
                className="flex min-w-0 flex-1 flex-col text-left"
                onClick={() => onChoose(profile)}
            >
                <span className="flex items-center gap-2 font-medium">
                    {active ? (
                        <CheckIcon className="size-4 text-primary" />
                    ) : null}
                    {profile?.name ??
                        t('profile.default', 'Default display profile')}
                </span>
                {profile?.description ? (
                    <span className="text-xs text-muted-foreground">
                        {profile.description}
                    </span>
                ) : null}
                {shared ? (
                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                        {profile.public ? (
                            <GlobeIcon className="size-3" />
                        ) : (
                            <UserIcon className="size-3" />
                        )}
                        {t('profile.shared_by', 'Shared by {{owner}}', {
                            owner: profile.owner?.username,
                        })}
                    </span>
                ) : null}
            </button>
            {profile ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon-xs">
                            <MoreVerticalIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => onEdit(profile.id)}>
                            {t('common.edit', 'Edit')}
                        </DropdownMenuItem>
                        {profile.capabilities.delete ? (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'profile.delete.title',
                                            'Delete profile "{{name}}"?',
                                            {name: profile.name}
                                        ),
                                        destructive: true,
                                        onConfirm: () => onDelete(profile.id),
                                    })
                                }
                            >
                                {t('common.delete', 'Delete')}
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : null}
        </div>
    );
}

export function CreateProfileDialog({
    open,
    onOpenChange,
    onCreated,
}: ModalProps & {onCreated?: (profile: DisplayProfile) => void}) {
    const {t} = useTranslation();
    const router = useRouter();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [isPublic, setIsPublic] = useState(false);
    const [loading, setLoading] = useState(false);
    const upsert = useProfileStore(s => s.upsert);
    const setCurrent = useProfileStore(s => s.setCurrent);
    const prefs = usePreferencesStore(s => s.preferences);

    const submit = async () => {
        setLoading(true);
        try {
            const {profile: _p, ...data} = prefs;
            const profile = await postProfile({
                name,
                description,
                public: isPublic,
                data: data as any,
            });
            upsert(profile);
            await setCurrent(profile);
            onOpenChange(false);
            onCreated?.(profile);
            router.push(routes.profileManage(profile.id, 'organize'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('profile.create', 'New profile')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <FormRow
                        label={t('common.name', 'Name')}
                        htmlFor="profile-name"
                    >
                        <Input
                            id="profile-name"
                            autoFocus
                            value={name}
                            onChange={e => setName(e.target.value)}
                        />
                    </FormRow>
                    <FormRow
                        label={t('common.description', 'Description')}
                        htmlFor="profile-desc"
                    >
                        <Textarea
                            id="profile-desc"
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                        />
                    </FormRow>
                    <LabeledControl label={t('common.public', 'Public')}>
                        <Switch
                            checked={isPublic}
                            onCheckedChange={setIsPublic}
                        />
                    </LabeledControl>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!name.trim()}
                        loading={loading}
                    >
                        {t('common.create', 'Create')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
