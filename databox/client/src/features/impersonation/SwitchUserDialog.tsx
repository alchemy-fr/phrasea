'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {CheckIcon} from 'lucide-react';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {Avatar} from '@/components/ui/misc';
import {Spinner} from '@/components/ui/loader';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {useAuth} from '@/lib/auth/AuthProvider';
import {
    getDisplayName,
    getImpersonableUsers,
    getImpersonationIdentity,
    type ImpersonableUser,
} from '@/lib/api/impersonation';
import {toastError} from '@/lib/utils/errors';

/**
 * Lets an admin act as another user to test permissions. Picking a user
 * reloads the app with that user's rights.
 */
export function SwitchUserDialog({open, onOpenChange}: ModalProps) {
    const {t} = useTranslation();
    const {user, realUser, impersonate} = useAuth();
    const [search, setSearch] = useState('');
    const query = useDebouncedValue(search.trim());
    const [switchingTo, setSwitchingTo] = useState<string>();

    const users = useQuery({
        queryKey: ['impersonation-users', query],
        queryFn: ({signal}) => getImpersonableUsers(query, signal),
        enabled: open,
    });

    const select = async (target: ImpersonableUser) => {
        if (switchingTo || target.id === user?.id) {
            return;
        }
        setSwitchingTo(target.id);
        try {
            impersonate(
                target.id === realUser?.id
                    ? realUser
                    : await getImpersonationIdentity(target.id)
            );
            // Keep the dialog up until the reload happens
        } catch (e) {
            setSwitchingTo(undefined);
            toastError(e);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="md" data-testid="switch-user-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {t('impersonation.title', 'Switch user')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'impersonation.help',
                            'Browse the app with the permissions of another user. Your actions are logged on your behalf.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody>
                    <Command shouldFilter={false} className="rounded-md border">
                        <CommandInput
                            value={search}
                            onValueChange={setSearch}
                            placeholder={t(
                                'impersonation.search',
                                'Search by name, username or email…'
                            )}
                        />
                        <CommandList className="max-h-80">
                            {users.isFetching && !users.data ? (
                                <div className="flex justify-center p-4">
                                    <Spinner />
                                </div>
                            ) : (
                                <CommandEmpty>
                                    {t('common.no_match', 'No match')}
                                </CommandEmpty>
                            )}
                            <CommandGroup>
                                {(users.data ?? []).map(u => (
                                    <CommandItem
                                        key={u.id}
                                        value={u.id}
                                        disabled={
                                            !u.enabled ||
                                            (!!switchingTo &&
                                                switchingTo !== u.id)
                                        }
                                        onSelect={() => void select(u)}
                                        data-testid={`switch-user-${u.username}`}
                                    >
                                        <Avatar
                                            name={getDisplayName(u)}
                                            size="sm"
                                        />
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate">
                                                {getDisplayName(u)}
                                                {u.id === realUser?.id ? (
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        {t(
                                                            'impersonation.you',
                                                            '(you)'
                                                        )}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {[u.username, u.email]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </div>
                                        {switchingTo === u.id ? (
                                            <Spinner />
                                        ) : u.id === user?.id ? (
                                            <CheckIcon />
                                        ) : null}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}
