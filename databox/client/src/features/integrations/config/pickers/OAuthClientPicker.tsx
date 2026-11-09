'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {CheckIcon, ListIcon, PlusIcon, TriangleAlertIcon} from 'lucide-react';
import type {OAuthClient} from '@/types/api';
import {getOAuthClients} from '@/lib/api/oauthClients';
import {useAuth} from '@/lib/auth/AuthProvider';
import {Button} from '@alchemy/next-ui/ui/button';
import {Badge} from '@alchemy/next-ui/ui/misc';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@alchemy/next-ui/ui/overlays';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@alchemy/next-ui/ui/command';
import {useDebouncedValue} from '@alchemy/next-ui/hooks/useDebouncedValue';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {cn} from '@alchemy/next-ui/utils/cn';
import {CreateOAuthClientDialog} from './CreateOAuthClientDialog';

type Props = {
    /** The text field of the client ID: it stays editable (free value, `${VAR}`) */
    children: ReactNode;
    value: string | undefined;
    onChange: (value: string | undefined) => void;
    /** Saved integration whose redirect URI the clients must allow */
    integrationId?: string;
    /** Title of the integration, to name a new client after it */
    integrationTitle?: string;
};

/**
 * Client ID of a Keycloak OAuth client: typed, or picked among the clients of
 * the realm, or created, when the Keycloak account of the user allows it.
 */
export function OAuthClientPicker({
    children,
    value,
    onChange,
    integrationId,
    integrationTitle,
}: Props) {
    const {canViewOAuthClients, canManageOAuthClients} = useAuth();

    if (!canViewOAuthClients && !canManageOAuthClients) {
        return <>{children}</>;
    }

    return (
        <div className="flex items-center gap-1">
            <div className="min-w-0 flex-1">{children}</div>
            {canViewOAuthClients ? (
                <OAuthClientList
                    value={value}
                    onSelect={onChange}
                    integrationId={integrationId}
                />
            ) : null}
            {canManageOAuthClients ? (
                <CreateOAuthClientButton
                    onCreate={onChange}
                    integrationTitle={integrationTitle}
                />
            ) : null}
        </div>
    );
}

function CreateOAuthClientButton({
    onCreate,
    integrationTitle,
}: {
    onCreate: (clientId: string) => void;
    integrationTitle?: string;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const create = async () => {
        const created = await openModal(CreateOAuthClientDialog, {
            integrationTitle,
        });
        if (created) {
            onCreate(created.clientId);
        }
    };

    return (
        <Button
            type="button"
            variant="outline"
            data-testid="oauth-client-create"
            onClick={() => void create()}
        >
            <PlusIcon />
            {t('integration.config.oauth_client.create', 'Create…')}
        </Button>
    );
}

function OAuthClientList({
    value,
    onSelect,
    integrationId,
}: {
    value: string | undefined;
    onSelect: (clientId: string) => void;
    integrationId?: string;
}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const query = useDebouncedValue(search, 250);
    const clients = useQuery({
        queryKey: ['oauth-clients', integrationId ?? null, query],
        queryFn: ({signal}) =>
            getOAuthClients({search: query, integrationId}, signal),
        enabled: open,
        staleTime: 30_000,
    });

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    data-testid="oauth-client-choose"
                >
                    <ListIcon />
                    {t('integration.config.oauth_client.choose', 'Choose…')}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-96 p-0" align="end">
                <Command shouldFilter={false}>
                    <CommandInput
                        value={search}
                        onValueChange={setSearch}
                        placeholder={t(
                            'integration.config.oauth_client.search',
                            'Search a client ID…'
                        )}
                    />
                    <CommandList>
                        {clients.isLoading ? (
                            <div className="flex justify-center py-4">
                                <Spinner />
                            </div>
                        ) : clients.isError ? (
                            <p className="p-3 text-sm text-destructive">
                                {getErrorMessage(clients.error)}
                            </p>
                        ) : (
                            <CommandEmpty>
                                {t(
                                    'integration.config.oauth_client.none',
                                    'No public client with the standard flow'
                                )}
                            </CommandEmpty>
                        )}
                        <CommandGroup>
                            {(clients.data ?? []).map(client => (
                                <CommandItem
                                    key={client.id}
                                    value={client.clientId}
                                    data-testid={`oauth-client-${client.clientId}`}
                                    onSelect={() => {
                                        onSelect(client.clientId);
                                        setOpen(false);
                                    }}
                                >
                                    <CheckIcon
                                        className={cn(
                                            'size-4 shrink-0',
                                            value === client.clientId
                                                ? 'opacity-100'
                                                : 'opacity-0'
                                        )}
                                    />
                                    <OAuthClientItem client={client} />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

function OAuthClientItem({client}: {client: OAuthClient}) {
    const {t} = useTranslation();

    return (
        <div className="flex min-w-0 flex-1 items-center gap-2">
            <div className="min-w-0 flex-1">
                <div className="truncate font-mono text-sm">
                    {client.clientId}
                </div>
                {client.name && client.name !== client.clientId ? (
                    <div className="truncate text-xs text-muted-foreground">
                        {client.name}
                    </div>
                ) : null}
            </div>
            {!client.redirectUriAllowed ? (
                <Badge
                    variant="warning"
                    title={t(
                        'integration.config.oauth_client.redirect_uri_not_allowed_help',
                        'Add the redirect URI of the integration to the client in Keycloak before using it'
                    )}
                >
                    <TriangleAlertIcon />
                    {t(
                        'integration.config.oauth_client.redirect_uri_not_allowed',
                        'Redirect URI not allowed'
                    )}
                </Badge>
            ) : null}
        </div>
    );
}
