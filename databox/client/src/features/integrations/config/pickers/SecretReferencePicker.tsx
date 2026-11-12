'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    BracesIcon,
    CheckIcon,
    ChevronsUpDownIcon,
    CircleAlertIcon,
    CornerDownLeftIcon,
    LockIcon,
    PlusIcon,
    ServerIcon,
    VariableIcon,
    XIcon,
} from 'lucide-react';
import type {WorkspaceEnv, WorkspaceSecret} from '@/types/api';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {Chip} from '@alchemy/next-ui/ui/misc';
import {
    Popover,
    PopoverAnchor,
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
import {isValidSecretName, parseReference} from '@/features/secrets/secretRef';
import {
    useSecretNames,
    useSystemEnvNames,
} from '@/features/secrets/useSecretNames';
import {CreateSecretDialog} from './CreateSecretDialog';
import {CreateVariableDialog} from './VariableDialog';

type Kind = 'secret' | 'variable' | 'system' | 'undefined';

type Props = {
    id: string;
    /** The name referenced, without `${}` */
    name: string;
    onChange: (name: string) => void;
    /** Omitted for an instance-wide integration: the instance secrets */
    workspaceId?: string;
    /** A sensitive field: the secrets come first, and only a secret is created */
    secret?: boolean;
    /** Name offered to a new secret or variable when nothing is searched */
    suggestedName?: string;
    defaultOpen?: boolean;
    invalid?: boolean;
};

/**
 * The name of the secret or variable a configuration value refers to, shown
 * as a chip: picked among the secrets and variables of the workspace and the
 * system variables of the instance (names only), created, or typed (a name
 * defined elsewhere than in the listing).
 */
export function SecretReferencePicker({
    id,
    name,
    onChange,
    workspaceId,
    secret,
    suggestedName,
    defaultOpen,
    invalid,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {secrets, envs, loading, forbidden} = useSecretNames({workspaceId});
    const system = useSystemEnvNames({workspaceId});
    const [open, setOpen] = useState(!!defaultOpen);
    const [search, setSearch] = useState('');
    // Listing the names needs to edit the workspace: free typing otherwise
    const listable = !forbidden;
    const typed = parseReference(search) ?? search.trim();

    const pick = (picked: string) => {
        onChange(picked);
        setOpen(false);
    };

    const create = async (kind: 'secret' | 'variable') => {
        setOpen(false);
        const initial = isValidSecretName(typed) ? typed : suggestedName;
        const created: WorkspaceSecret | WorkspaceEnv | undefined =
            kind === 'secret'
                ? await openModal(CreateSecretDialog, {
                      workspaceId,
                      name: initial,
                  })
                : await openModal(CreateVariableDialog, {
                      workspaceId,
                      name: initial,
                  });
        if (created) {
            onChange(created.name);
        }
    };

    const matches = (n: string) =>
        search === '' || n.toLowerCase().includes(typed.toLowerCase());

    // Same order as the resolution: secret, variable, then system variable
    const variable = envs?.find(e => e.name === name);
    const kind: Kind | undefined =
        !listable || loading || name === ''
            ? undefined
            : secrets?.some(s => s.name === name)
              ? 'secret'
              : variable
                ? 'variable'
                : system.names?.includes(name)
                  ? 'system'
                  : secrets && envs && !system.loading
                    ? 'undefined'
                    : undefined;

    const secretGroup = secrets?.some(s => matches(s.name)) ? (
        <CommandGroup
            key="secrets"
            heading={t('integration.config.secret_ref.secrets', 'Secrets')}
        >
            {secrets
                .filter(s => matches(s.name))
                .map(s => (
                    <NameItem
                        key={s.id}
                        name={s.name}
                        icon={<LockIcon />}
                        selected={s.name === name}
                        testId={`secret-ref-secret-${s.name}`}
                        onSelect={() => pick(s.name)}
                    />
                ))}
        </CommandGroup>
    ) : null;
    const variableGroup = envs?.some(e => matches(e.name)) ? (
        <CommandGroup
            key="variables"
            heading={t('integration.config.secret_ref.variables', 'Variables')}
        >
            {envs
                .filter(e => matches(e.name))
                .map(e => (
                    <NameItem
                        key={e.id}
                        name={e.name}
                        icon={<BracesIcon />}
                        detail={e.value}
                        selected={e.name === name}
                        testId={`secret-ref-env-${e.name}`}
                        onSelect={() => pick(e.name)}
                    />
                ))}
        </CommandGroup>
    ) : null;
    const systemGroup = system.names?.some(matches) ? (
        <CommandGroup
            key="system"
            heading={t('integration.config.secret_ref.system', 'System')}
        >
            {system.names.filter(matches).map(n => (
                <NameItem
                    key={n}
                    name={n}
                    icon={<ServerIcon />}
                    selected={n === name}
                    testId={`secret-ref-system-${n}`}
                    onSelect={() => pick(n)}
                />
            ))}
        </CommandGroup>
    ) : null;
    const known =
        secrets?.some(s => s.name === typed) ||
        envs?.some(e => e.name === typed) ||
        system.names?.includes(typed);
    const createSecret = (
        <CommandItem
            key="secret"
            value="__create_secret__"
            data-testid="secret-ref-create"
            onSelect={() => void create('secret')}
        >
            <PlusIcon />
            {t('integration.config.secret_ref.create', 'New secret…')}
        </CommandItem>
    );
    const createVariable = (
        <CommandItem
            key="variable"
            value="__create_variable__"
            data-testid="secret-ref-create-variable"
            onSelect={() => void create('variable')}
        >
            <PlusIcon />
            {t(
                'integration.config.secret_ref.create_variable',
                'New variable…'
            )}
        </CommandItem>
    );

    return (
        <div>
            <Popover
                open={open}
                onOpenChange={o => {
                    setOpen(o);
                    setSearch('');
                }}
            >
                <PopoverAnchor asChild>
                    <div
                        className={cn(
                            'flex h-9 w-full min-w-0 items-center rounded-md border border-input shadow-xs transition-colors focus-within:ring-2 focus-within:ring-ring/60',
                            invalid && 'border-destructive'
                        )}
                    >
                        <PopoverTrigger asChild>
                            <button
                                type="button"
                                id={id}
                                data-testid="secret-ref-choose"
                                className="flex h-full min-w-0 flex-1 items-center gap-2 rounded-md px-2 text-left text-sm outline-none"
                            >
                                {name !== '' ? (
                                    <ReferenceChip name={name} kind={kind} />
                                ) : (
                                    <span className="truncate pl-1 text-muted-foreground">
                                        {secret
                                            ? t(
                                                  'integration.config.secret_ref.placeholder_secret',
                                                  'Choose a secret…'
                                              )
                                            : t(
                                                  'integration.config.secret_ref.placeholder',
                                                  'Choose a variable or a secret…'
                                              )}
                                    </span>
                                )}
                                <ChevronsUpDownIcon className="ml-auto size-4 shrink-0 opacity-50" />
                            </button>
                        </PopoverTrigger>
                        {name !== '' ? (
                            <button
                                type="button"
                                className="mr-1 flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60"
                                aria-label={t(
                                    'integration.config.secret_ref.clear',
                                    'Remove the reference'
                                )}
                                data-testid="secret-ref-clear"
                                onClick={() => onChange('')}
                            >
                                <XIcon className="size-4" />
                            </button>
                        ) : null}
                    </div>
                </PopoverAnchor>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) min-w-80 p-0"
                    align="start"
                >
                    <Command shouldFilter={false}>
                        <CommandInput
                            value={search}
                            onValueChange={setSearch}
                            data-testid="secret-ref-search"
                            placeholder={
                                listable
                                    ? t(
                                          'integration.config.secret_ref.search',
                                          'Search or type a name…'
                                      )
                                    : t(
                                          'integration.config.secret_ref.type',
                                          'Type the name of a secret or variable…'
                                      )
                            }
                        />
                        <CommandList>
                            {listable && (loading || system.loading) ? (
                                <div className="flex justify-center py-4">
                                    <Spinner />
                                </div>
                            ) : null}
                            {listable
                                ? secret
                                    ? [secretGroup, variableGroup, systemGroup]
                                    : [variableGroup, secretGroup, systemGroup]
                                : null}
                            {isValidSecretName(typed) && !known ? (
                                <CommandGroup>
                                    <CommandItem
                                        value="__use__"
                                        data-testid="secret-ref-use"
                                        onSelect={() => pick(typed)}
                                    >
                                        <CornerDownLeftIcon />
                                        <span className="truncate">
                                            {t(
                                                'integration.config.secret_ref.use',
                                                'Use {{name}}',
                                                {name: typed}
                                            )}
                                        </span>
                                    </CommandItem>
                                </CommandGroup>
                            ) : null}
                            {listable &&
                            !loading &&
                            !secretGroup &&
                            !variableGroup &&
                            !systemGroup &&
                            search === '' ? (
                                <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                                    {t(
                                        'integration.config.secret_ref.empty',
                                        'No secret nor variable yet'
                                    )}
                                </p>
                            ) : null}
                            {listable && !loading ? (
                                <CommandGroup>
                                    {secret
                                        ? createSecret
                                        : [createVariable, createSecret]}
                                </CommandGroup>
                            ) : null}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            <ReferenceStatus
                name={name}
                workspaceId={workspaceId}
                kind={kind}
                value={variable?.value}
                secretField={secret}
            />
        </div>
    );
}

function ReferenceChip({name, kind}: {name: string; kind?: Kind}) {
    return (
        <Chip
            size="md"
            className={cn(
                'min-w-0 font-mono',
                kind === 'undefined' &&
                    'border-dashed bg-transparent text-muted-foreground'
            )}
            icon={
                kind === 'secret' ? (
                    <LockIcon className="size-3.5 shrink-0" />
                ) : kind === 'variable' ? (
                    <BracesIcon className="size-3.5 shrink-0" />
                ) : kind === 'system' ? (
                    <ServerIcon className="size-3.5 shrink-0" />
                ) : kind === 'undefined' ? (
                    <CircleAlertIcon className="size-3.5 shrink-0" />
                ) : (
                    <VariableIcon className="size-3.5 shrink-0 opacity-60" />
                )
            }
            data-testid="secret-ref-chip"
            data-kind={kind}
        >
            {name}
        </Chip>
    );
}

function NameItem({
    name,
    icon,
    detail,
    selected,
    testId,
    onSelect,
}: {
    name: string;
    icon: ReactNode;
    detail?: string;
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
            <span className="shrink-0 font-mono">{name}</span>
            {detail ? (
                <span className="ml-auto min-w-0 truncate text-xs text-muted-foreground">
                    {detail}
                </span>
            ) : null}
        </CommandItem>
    );
}

/** What the reference resolves to, as far as the listing tells */
function ReferenceStatus({
    name,
    workspaceId,
    kind,
    value,
    secretField,
}: {
    name: string;
    workspaceId?: string;
    kind?: Kind;
    value?: string;
    secretField?: boolean;
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
                    <LockIcon className="size-3.5 shrink-0" />
                    {t(
                        'integration.config.secret_ref.is_secret',
                        'Secret: its value is never shown'
                    )}
                </>
            );
            break;
        case 'variable':
            content = secretField ? (
                <>
                    <CircleAlertIcon className="size-3.5 shrink-0 text-warning-foreground" />
                    {t(
                        'integration.config.secret_ref.is_variable_for_secret',
                        'Variable, stored in clear: prefer a secret for this field'
                    )}
                </>
            ) : (
                <>
                    <BracesIcon className="size-3.5 shrink-0" />
                    <span className="shrink-0">
                        {t(
                            'integration.config.secret_ref.is_variable',
                            'Variable'
                        )}
                    </span>
                    {value !== undefined ? (
                        <span
                            className="truncate font-mono"
                            data-testid="secret-ref-value"
                        >
                            = {value}
                        </span>
                    ) : null}
                </>
            );
            break;
        case 'system':
            content = (
                <>
                    <ServerIcon className="size-3.5 shrink-0" />
                    {t(
                        'integration.config.secret_ref.is_system',
                        'System variable of the instance'
                    )}
                </>
            );
            break;
        case 'undefined':
            content = (
                <>
                    <CircleAlertIcon className="size-3.5 shrink-0 text-warning-foreground" />
                    {workspaceId
                        ? t(
                              'integration.config.secret_ref.undefined',
                              'Not defined in this workspace'
                          )
                        : t(
                              'integration.config.secret_ref.undefined_instance',
                              'Not defined on the instance'
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
            className="mt-1 flex min-w-0 items-center gap-1 text-xs text-muted-foreground"
            data-testid="secret-ref-status"
        >
            {content}
        </p>
    );
}
