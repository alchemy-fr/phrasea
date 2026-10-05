'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    CheckIcon,
    InfoIcon,
    MoreVerticalIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
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
import {Tooltip} from '@/components/ui/overlays';
import {ProfileOwnership} from './ProfileOwnership';
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
    const {profiles, current, setCurrent, remove, next, loadMore} =
        useProfileStore();

    const choose = async (profile: DisplayProfile | undefined) => {
        await setCurrent(profile);
        onOpenChange(false);
    };
    const edit = (id: string, tab?: string) => {
        onOpenChange(false);
        router.push(routes.profileManage(id, tab));
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('profile.select.title', 'Display profiles')}
            hideCancel
            submitLabel={t('common.close', 'Close')}
            bodyClassName="space-y-2"
            footerStart={
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
            }
        >
            <ProfileRow
                profile={undefined}
                current={current}
                userId={user?.id}
                onChoose={choose}
                onDelete={remove}
                onEdit={edit}
            />
            {profiles.map(p => (
                <ProfileRow
                    key={p.id}
                    profile={p}
                    current={current}
                    userId={user?.id}
                    onChoose={choose}
                    onDelete={remove}
                    onEdit={edit}
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
        </FormDialog>
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
    onEdit: (id: string, tab?: string) => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const active = (profile?.id ?? null) === (current?.id ?? null);
    const shared = profile && profile.owner && profile.owner.id !== userId;

    return (
        <div
            data-testid="profile-row"
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
                    <span className="truncate">
                        {profile?.name ??
                            t('profile.default', 'Default display profile')}
                    </span>
                    {profile ? (
                        <ProfileOwnership profile={profile} userId={userId} />
                    ) : null}
                </span>
                {profile?.description ? (
                    <span className="text-xs text-muted-foreground">
                        {profile.description}
                    </span>
                ) : null}
                {shared ? (
                    <span className="text-xs text-muted-foreground">
                        {t('profile.shared_by', 'Shared by {{owner}}', {
                            owner: profile.owner?.username,
                        })}
                    </span>
                ) : null}
            </button>
            {profile ? (
                <>
                    <Tooltip
                        content={
                            profile.capabilities.edit
                                ? t('common.edit', 'Edit')
                                : t('common.details', 'Details')
                        }
                    >
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            data-testid="profile-row-edit"
                            aria-label={
                                profile.capabilities.edit
                                    ? t('common.edit', 'Edit')
                                    : t('common.details', 'Details')
                            }
                            onClick={() =>
                                onEdit(
                                    profile.id,
                                    profile.capabilities.edit
                                        ? 'attributes'
                                        : undefined
                                )
                            }
                        >
                            {profile.capabilities.edit ? (
                                <PencilIcon />
                            ) : (
                                <InfoIcon />
                            )}
                        </Button>
                    </Tooltip>
                    {profile.capabilities.delete ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    data-testid="profile-row-menu"
                                    aria-label={t('common.more', 'More')}
                                >
                                    <MoreVerticalIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
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
                                            onConfirm: () =>
                                                onDelete(profile.id),
                                        })
                                    }
                                >
                                    <Trash2Icon />{' '}
                                    {t('common.delete', 'Delete')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </>
            ) : null}
        </div>
    );
}

export function CreateProfileDialog({
    open,
    onOpenChange,
    resolve,
    onCreated,
}: ModalProps<DisplayProfile> & {
    onCreated?: (profile: DisplayProfile) => void;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [isPublic, setIsPublic] = useState(false);
    const upsert = useProfileStore(s => s.upsert);
    const setCurrent = useProfileStore(s => s.setCurrent);
    const prefs = usePreferencesStore(s => s.preferences);

    const submit = async () => {
        const {profile: _p, ...data} = prefs;
        const profile = await postProfile({
            name,
            description,
            public: isPublic,
            data: data as any,
        });
        upsert(profile);
        await setCurrent(profile);
        resolve?.(profile);
        onCreated?.(profile);
        router.push(routes.profileManage(profile.id, 'attributes'));
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('profile.create', 'New profile')}
            submitLabel={t('common.create', 'Create')}
            canSubmit={!!name.trim()}
            dirty={!!name.trim() || !!description.trim()}
            onSubmit={submit}
        >
            <FormRow label={t('common.name', 'Name')} htmlFor="profile-name">
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
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
        </FormDialog>
    );
}
