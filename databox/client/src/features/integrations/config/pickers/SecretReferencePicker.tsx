'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    BracesIcon,
    CheckIcon,
    CircleAlertIcon,
    ListIcon,
    LockIcon,
    PlusIcon,
} from 'lucide-react';
import type {WorkspaceSecret} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {Input} from '@alchemy/next-ui/ui/input';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@alchemy/next-ui/ui/overlays';
import {
    Command,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@alchemy/next-ui/ui/command';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {cn} from '@alchemy/next-ui/utils/cn';
import {parseReference} from '@/features/secrets/secretRef';
import {useSecretNames} from '@/features/secrets/useSecretNames';
import {CreateSecretDialog} from './CreateSecretDialog';

type Props = {
    id: string;
    /** The name referenced, without `${}` */
    name: string;
    onChange: (name: string) => void;
    /** Omitted for an instance-wide integration: the instance secrets */
    workspaceId?: string;
    required?: boolean;
    invalid?: boolean;
};

/**
 * The name of the secret or variable a configuration value refers to: typed
 * (it may come from the environment of the instance, which is not listed),
 * picked among the secrets and variables of the workspace, or created.
 */
export function SecretReferencePicker({
    id,
    name,
    onChange,
    workspaceId,
    required,
    invalid,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {secrets, envs, loading, forbidden} = useSecretNames({workspaceId});
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    // Listing the names needs to edit the workspace: free typing otherwise
    const listable = !forbidden;

    const create = async (suggested: string) => {
        setOpen(false);
        const created: WorkspaceSecret | undefined = await openModal(
            CreateSecretDialog,
            {workspaceId, name: suggested}
        );
        if (created) {
            onChange(created.name);
        }
    };

    const matches = (n: string) =>
        search === '' || n.toLowerCase().includes(search.toLowerCase());

    return (
        <div>
            <div className="flex items-center gap-1">
                <div className="flex min-w-0 flex-1 items-center gap-1 font-mono">
                    <span className="text-muted-foreground" aria-hidden>
                        {'${'}
                    </span>
                    <Input
                        id={id}
                        value={name}
                        onChange={e =>
                            onChange(
                                parseReference(e.target.value) ??
                                    e.target.value.trim()
                            )
                        }
                        placeholder="NAME"
                        required={required}
                        aria-invalid={invalid}
                        autoComplete="off"
                        spellCheck={false}
                        className="font-mono"
                    />
                    <span className="text-muted-foreground" aria-hidden>
                        {'}'}
                    </span>
                </div>
                {listable ? (
                    <Popover
                        open={open}
                        onOpenChange={o => {
                            setOpen(o);
                            setSearch('');
                        }}
                    >
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                data-testid="secret-ref-choose"
                            >
                                <ListIcon />
                                {t(
                                    'integration.config.secret_ref.choose',
                                    'Choose…'
                                )}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-80 p-0" align="end">
                            <Command shouldFilter={false}>
                                <CommandInput
                                    value={search}
                                    onValueChange={setSearch}
                                    placeholder={t(
                                        'integration.config.secret_ref.search',
                                        'Search a name…'
                                    )}
                                />
                                <CommandList>
                                    {loading ? (
                                        <div className="flex justify-center py-4">
                                            <Spinner />
                                        </div>
                                    ) : null}
                                    {secrets?.some(s => matches(s.name)) ? (
                                        <CommandGroup
                                            heading={t(
                                                'integration.config.secret_ref.secrets',
                                                'Secrets'
                                            )}
                                        >
                                            {secrets
                                                .filter(s => matches(s.name))
                                                .map(s => (
                                                    <NameItem
                                                        key={s.id}
                                                        name={s.name}
                                                        icon={<LockIcon />}
                                                        selected={
                                                            s.name === name
                                                        }
                                                        testId={`secret-ref-secret-${s.name}`}
                                                        onSelect={() => {
                                                            onChange(s.name);
                                                            setOpen(false);
                                                        }}
                                                    />
                                                ))}
                                        </CommandGroup>
                                    ) : null}
                                    {envs?.some(e => matches(e.name)) ? (
                                        <CommandGroup
                                            heading={t(
                                                'integration.config.secret_ref.variables',
                                                'Variables'
                                            )}
                                        >
                                            {envs
                                                .filter(e => matches(e.name))
                                                .map(e => (
                                                    <NameItem
                                                        key={e.id}
                                                        name={e.name}
                                                        icon={<BracesIcon />}
                                                        selected={
                                                            e.name === name
                                                        }
                                                        testId={`secret-ref-env-${e.name}`}
                                                        onSelect={() => {
                                                            onChange(e.name);
                                                            setOpen(false);
                                                        }}
                                                    />
                                                ))}
                                        </CommandGroup>
                                    ) : null}
                                    {!loading ? (
                                        <CommandGroup>
                                            <CommandItem
                                                value="__create__"
                                                data-testid="secret-ref-create"
                                                onSelect={() =>
                                                    void create(
                                                        (search || name).trim()
                                                    )
                                                }
                                            >
                                                <PlusIcon />
                                                {t(
                                                    'integration.config.secret_ref.create',
                                                    'New secret…'
                                                )}
                                            </CommandItem>
                                        </CommandGroup>
                                    ) : null}
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                ) : null}
            </div>
            <ReferenceStatus
                name={name}
                workspaceId={workspaceId}
                kind={
                    !listable || loading
                        ? undefined
                        : secrets?.some(s => s.name === name)
                          ? 'secret'
                          : envs?.some(e => e.name === name)
                            ? 'variable'
                            : secrets && envs
                              ? 'undefined'
                              : undefined
                }
            />
        </div>
    );
}

function NameItem({
    name,
    icon,
    selected,
    testId,
    onSelect,
}: {
    name: string;
    icon: ReactNode;
    selected: boolean;
    testId: string;
    onSelect: () => void;
}) {
    return (
        <CommandItem value={name} data-testid={testId} onSelect={onSelect}>
            <CheckIcon
                className={cn(
                    'size-4 shrink-0',
                    selected ? 'opacity-100' : 'opacity-0'
                )}
            />
            {icon}
            <span className="truncate font-mono">{name}</span>
        </CommandItem>
    );
}

/** What the reference resolves to, as far as the listing tells */
function ReferenceStatus({
    name,
    workspaceId,
    kind,
}: {
    name: string;
    workspaceId?: string;
    kind?: 'secret' | 'variable' | 'undefined';
}) {
    const {t} = useTranslation();
    if (name === '') {
        return null;
    }

    let content;
    switch (kind) {
        case 'secret':
            content = (
                <>
                    <LockIcon className="size-3.5" />
                    {t('integration.config.secret_ref.is_secret', 'Secret')}
                </>
            );
            break;
        case 'variable':
            content = (
                <>
                    <BracesIcon className="size-3.5" />
                    {t('integration.config.secret_ref.is_variable', 'Variable')}
                </>
            );
            break;
        case 'undefined':
            content = (
                <>
                    <CircleAlertIcon className="size-3.5 text-warning-foreground" />
                    {workspaceId
                        ? t(
                              'integration.config.secret_ref.undefined',
                              'Not defined in this workspace: resolved from the environment of the instance ({{env}}), or empty',
                              {env: `PWI_${name}`}
                          )
                        : t(
                              'integration.config.secret_ref.undefined_instance',
                              'Not defined on the instance: resolved from its environment ({{env}}), or empty',
                              {env: `PWI_${name}`}
                          )}
                </>
            );
            break;
        default:
            content = t(
                'integration.config.env_ref',
                'Reference to a workspace secret or variable'
            );
    }

    return (
        <p
            className="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
            data-testid="secret-ref-status"
        >
            {content}
        </p>
    );
}
