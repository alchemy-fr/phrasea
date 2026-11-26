'use client';

import {FormEvent, useCallback, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    keepPreviousData,
    useInfiniteQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    PlusIcon,
    SearchIcon,
    Trash2Icon,
    UserMinusIcon,
    UsersIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '../ui/dialog';
import {Button} from '../ui/button';
import {FieldError, Input, Label} from '../ui/input';
import {Spinner} from '../ui/loader';
import {Avatar, EmptyState} from '../ui/misc';
import {useConfirm} from '../ui/confirm';
import {AsyncCombobox, type ComboPage} from '../form/AsyncCombobox';
import {useModals, type ModalProps} from '../modals/ModalProvider';
import {useDebouncedValue} from '../hooks/useDebouncedValue';
import {useInfiniteScroll} from '../hooks/useInfiniteScroll';
import {cn} from '../utils/cn';
import {getErrorMessage, toastError} from '../utils/errors';
import {
    CreateGroupDialog,
    getNameError,
    invalidateGroups,
} from './CreateGroupDialog';
import {
    GROUPS_PAGE_SIZE,
    groupsQueryKey,
    memberDisplayName,
    type GroupMember,
    type GroupsApi,
    type KeycloakGroup,
} from './types';

/** The offset of the next page, as long as the last one is full */
function nextOffset<T>(last: T[], all: T[][]): number | undefined {
    return last.length === GROUPS_PAGE_SIZE
        ? all.length * GROUPS_PAGE_SIZE
        : undefined;
}

type Props = ModalProps<void> & {
    api: GroupsApi;
};

/**
 * Creates, renames and deletes the Keycloak groups and changes their members,
 * with the token of the user (Keycloak checks its `manage-users` role).
 */
export function ManageGroupsDialog({open, onOpenChange, api}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [selected, setSelected] = useState<KeycloakGroup>();
    const [search, setSearch] = useState('');
    const query = useDebouncedValue(search.trim(), 250);
    const listRef = useRef<HTMLDivElement>(null);

    const groups = useInfiniteQuery({
        queryKey: [...groupsQueryKey, 'list', query],
        queryFn: ({signal, pageParam}) =>
            api.searchGroups(query, pageParam, GROUPS_PAGE_SIZE, signal),
        initialPageParam: 0,
        getNextPageParam: nextOffset,
        enabled: open,
        // The groups found so far stay listed while the next search runs
        placeholderData: keepPreviousData,
    });
    const {hasNextPage, isFetchingNextPage, fetchNextPage} = groups;
    const sentinelRef = useInfiniteScroll(
        listRef,
        () => void fetchNextPage(),
        open && hasNextPage && !isFetchingNextPage
    );
    const items = groups.data?.pages.flat() ?? [];

    const create = async () => {
        const created = await openModal(CreateGroupDialog, {
            api,
            initialName: search.trim() || undefined,
        });
        if (created) {
            setSelected(created);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                size="xl"
                className="h-[min(44rem,calc(100dvh-2rem))]"
                data-testid="manage-groups-dialog"
            >
                <DialogHeader>
                    <DialogTitle>{t('groups.title', 'Groups')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'groups.description',
                            'The groups of the identity provider: what is granted to a group applies to its members.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody className="grid min-h-0 grid-rows-[auto_minmax(0,1fr)] gap-4 overflow-hidden md:grid-cols-[18rem_minmax(0,1fr)] md:grid-rows-1">
                    <div className="flex max-h-72 min-h-0 flex-col gap-2 md:max-h-none">
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    data-testid="groups-search"
                                    aria-label={t(
                                        'groups.search',
                                        'Search groups…'
                                    )}
                                    placeholder={t(
                                        'groups.search',
                                        'Search groups…'
                                    )}
                                    className="pl-8"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                />
                            </div>
                            <Button
                                data-testid="groups-new"
                                onClick={() => void create()}
                                aria-label={t('groups.new', 'New group')}
                            >
                                <PlusIcon />
                                <span className="hidden sm:inline">
                                    {t('groups.new', 'New group')}
                                </span>
                            </Button>
                        </div>
                        <div
                            ref={listRef}
                            role="listbox"
                            aria-label={t('groups.title', 'Groups')}
                            className="min-h-0 flex-1 overflow-y-auto rounded-md border p-1"
                        >
                            {groups.isLoading ? (
                                <div className="flex justify-center py-4">
                                    <Spinner />
                                </div>
                            ) : groups.isError ? (
                                <p className="p-3 text-sm text-destructive">
                                    {getErrorMessage(groups.error)}
                                </p>
                            ) : items.length === 0 ? (
                                <p className="p-3 text-center text-sm text-muted-foreground">
                                    {t('groups.empty', 'No group')}
                                </p>
                            ) : (
                                items.map(group => (
                                    <button
                                        key={group.id}
                                        type="button"
                                        role="option"
                                        aria-selected={
                                            selected?.id === group.id
                                        }
                                        data-testid="group-item"
                                        onClick={() => setSelected(group)}
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent',
                                            selected?.id === group.id &&
                                                'bg-accent font-medium'
                                        )}
                                    >
                                        <UsersIcon className="size-4 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {group.name}
                                        </span>
                                    </button>
                                ))
                            )}
                            <div ref={sentinelRef} className="h-px" />
                            {isFetchingNextPage ? (
                                <div className="flex justify-center py-2">
                                    <Spinner />
                                </div>
                            ) : null}
                        </div>
                    </div>
                    <div className="min-h-0 overflow-y-auto">
                        {selected ? (
                            <GroupPanel
                                key={selected.id}
                                group={selected}
                                api={api}
                                onRenamed={setSelected}
                                onDeleted={() => setSelected(undefined)}
                            />
                        ) : (
                            <EmptyState
                                icon={<UsersIcon />}
                                title={t(
                                    'groups.none_selected',
                                    'Select a group to manage its members.'
                                )}
                            />
                        )}
                    </div>
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}

function GroupPanel({
    group,
    api,
    onRenamed,
    onDeleted,
}: {
    group: KeycloakGroup;
    api: GroupsApi;
    onRenamed: (group: KeycloakGroup) => void;
    onDeleted: () => void;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const confirm = useConfirm();
    const [name, setName] = useState(group.name);
    const [nameError, setNameError] = useState<string>();
    const [renaming, setRenaming] = useState(false);
    const canRename = !!name.trim() && name.trim() !== group.name;

    const rename = async (e: FormEvent) => {
        e.preventDefault();
        if (!canRename) {
            return;
        }
        setRenaming(true);
        try {
            const renamed = await api.renameGroup(group.id, name.trim());
            invalidateGroups(queryClient);
            toast.success(t('groups.renamed', 'Group renamed'));
            onRenamed(renamed);
        } catch (err) {
            const error = getNameError(err);
            if (error) {
                setNameError(error);
            } else {
                toastError(err);
            }
        } finally {
            setRenaming(false);
        }
    };

    const remove = () =>
        confirm({
            title: t('groups.delete_title', 'Delete the group {{name}}?', {
                name: group.name,
            }),
            description: t(
                'groups.delete_description',
                'Its members leave it, and the permissions granted to the group no longer apply.'
            ),
            textToType: group.name,
            destructive: true,
            confirmLabel: t('groups.delete', 'Delete'),
            onConfirm: () => api.deleteGroup(group.id),
            onConfirmed: () => {
                invalidateGroups(queryClient);
                toast.success(t('groups.deleted', 'Group deleted'));
                onDeleted();
            },
        });

    return (
        <div className="space-y-4" data-testid="group-panel">
            <form onSubmit={rename} className="space-y-1.5">
                <Label htmlFor="group-name">{t('groups.name', 'Name')}</Label>
                <div className="flex gap-2">
                    <Input
                        id="group-name"
                        data-testid="group-name"
                        autoComplete="off"
                        maxLength={255}
                        value={name}
                        aria-invalid={!!nameError}
                        onChange={e => {
                            setName(e.target.value);
                            setNameError(undefined);
                        }}
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        data-testid="group-rename"
                        disabled={!canRename}
                        loading={renaming}
                    >
                        {t('groups.rename', 'Rename')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-testid="group-delete"
                        aria-label={t('groups.delete', 'Delete')}
                        className="text-destructive hover:text-destructive"
                        onClick={() => void remove()}
                    >
                        <Trash2Icon />
                    </Button>
                </div>
                <FieldError>{nameError}</FieldError>
            </form>
            <GroupMembers group={group} api={api} />
        </div>
    );
}

function GroupMembers({group, api}: {group: KeycloakGroup; api: GroupsApi}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const confirm = useConfirm();
    const listRef = useRef<HTMLDivElement>(null);
    const membersKey = [...groupsQueryKey, 'members', group.id];

    const members = useInfiniteQuery({
        queryKey: membersKey,
        queryFn: ({signal, pageParam}) =>
            api.getMembers(group.id, pageParam, GROUPS_PAGE_SIZE, signal),
        initialPageParam: 0,
        getNextPageParam: nextOffset,
    });
    const {hasNextPage, isFetchingNextPage, fetchNextPage} = members;
    const sentinelRef = useInfiniteScroll(
        listRef,
        () => void fetchNextPage(),
        !!hasNextPage && !isFetchingNextPage
    );
    const items = members.data?.pages.flat() ?? [];
    const refresh = () =>
        void queryClient.invalidateQueries({queryKey: membersKey});

    const loadUsers = useCallback(
        async (
            query: string,
            signal: AbortSignal,
            cursor?: string
        ): Promise<ComboPage<GroupMember>> => {
            const offset = Number(cursor ?? 0);
            const users = await api.searchUsers(
                query,
                offset,
                GROUPS_PAGE_SIZE,
                signal
            );

            return {
                options: users.map(user => ({
                    value: user.id,
                    label: memberDisplayName(user),
                    item: user,
                })),
                next:
                    users.length === GROUPS_PAGE_SIZE
                        ? String(offset + GROUPS_PAGE_SIZE)
                        : undefined,
            };
        },
        [api]
    );

    const add = async (userId: string | undefined) => {
        if (!userId) {
            return;
        }
        try {
            await api.addMember(group.id, userId);
        } catch (e) {
            toastError(e);

            return;
        }
        refresh();
    };

    const removeMember = (member: GroupMember) =>
        confirm({
            title: t(
                'groups.remove_member_title',
                'Remove {{name}} from the group?',
                {name: memberDisplayName(member)}
            ),
            description: t(
                'groups.remove_member_description',
                'The permissions granted to the group no longer apply to this user.'
            ),
            destructive: true,
            confirmLabel: t('groups.remove_member', 'Remove'),
            onConfirm: () => api.removeMember(group.id, member.id),
            onConfirmed: refresh,
        });

    return (
        <div className="space-y-2">
            <div>
                <h3 className="text-sm font-semibold">
                    {t('groups.members', 'Members')}
                </h3>
                <p className="text-xs text-muted-foreground">
                    {t(
                        'groups.membership_delay',
                        'A change applies to a signed-in user once their session is refreshed.'
                    )}
                </p>
            </div>
            <AsyncCombobox<GroupMember>
                queryKey={[...groupsQueryKey, 'users']}
                loadPage={loadUsers}
                value={undefined}
                onChange={add}
                placeholder={t('groups.add_member', 'Add a member…')}
            />
            <div
                ref={listRef}
                data-testid="group-members"
                className="max-h-96 overflow-y-auto rounded-md border"
            >
                {members.isLoading ? (
                    <div className="flex justify-center py-4">
                        <Spinner />
                    </div>
                ) : members.isError ? (
                    <p className="p-3 text-sm text-destructive">
                        {getErrorMessage(members.error)}
                    </p>
                ) : items.length === 0 ? (
                    <p className="p-3 text-center text-sm text-muted-foreground">
                        {t('groups.no_members', 'No member')}
                    </p>
                ) : (
                    <ul className="divide-y">
                        {items.map(member => (
                            <li
                                key={member.id}
                                data-testid="group-member"
                                className="flex items-center gap-3 px-3 py-2"
                            >
                                <Avatar
                                    name={memberDisplayName(member)}
                                    size="sm"
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm">
                                        {memberDisplayName(member)}
                                    </div>
                                    {member.email ? (
                                        <div className="truncate text-xs text-muted-foreground">
                                            {member.email}
                                        </div>
                                    ) : null}
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    data-testid="group-member-remove"
                                    aria-label={t(
                                        'groups.remove_member',
                                        'Remove'
                                    )}
                                    onClick={() => void removeMember(member)}
                                >
                                    <UserMinusIcon />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
                <div ref={sentinelRef} className="h-px" />
                {isFetchingNextPage ? (
                    <div className="flex justify-center py-2">
                        <Spinner />
                    </div>
                ) : null}
            </div>
        </div>
    );
}
