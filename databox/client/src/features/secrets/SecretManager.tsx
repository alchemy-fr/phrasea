'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    BracesIcon,
    KeyRoundIcon,
    LockIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {
    WorkspaceEnv,
    WorkspaceIntegration,
    WorkspaceSecret,
} from '@/types/api';
import {
    getGlobalIntegrations,
    getWorkspaceIntegrations,
} from '@/lib/api/integrations';
import {
    deleteWorkspaceEnv,
    deleteWorkspaceSecret,
    envsQueryKey,
    secretsQueryKey,
} from '@/lib/api/secrets';
import {Button} from '@alchemy/next-ui/ui/button';
import {Skeleton} from '@alchemy/next-ui/ui/misc';
import {ConfirmDialog} from '@alchemy/next-ui/ui/confirm';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {LoadError} from '@/components/modals/LoadPlaceholder';
import {integrationLabel} from '@/features/integrations/integrationLabel';
import {
    CreateSecretDialog,
    ReplaceSecretValueDialog,
} from '@/features/integrations/config/pickers/CreateSecretDialog';
import {
    CreateVariableDialog,
    EditVariableDialog,
} from '@/features/integrations/config/pickers/VariableDialog';
import {configRefersTo} from './secretRef';
import {secretScopeKey, useSecretNames} from './useSecretNames';

type Props = {
    /** Omitted for the secrets of the instance (administrators only) */
    workspaceId?: string;
};

/**
 * The secrets and variables of a workspace (or of the instance): their names
 * and the integrations referring to them. The value of a secret is replaced
 * but never shown, the one of a variable is edited in clear.
 */
export function SecretManager({workspaceId}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const scope = {workspaceId};
    const {secrets, envs, loading, error} = useSecretNames(scope);
    // References are only known to the client: the integrations of the same scope are scanned
    const integrations = useQuery({
        queryKey: ['integrations', 'manage', secretScopeKey(scope)],
        queryFn: () =>
            workspaceId
                ? getWorkspaceIntegrations(workspaceId)
                : getGlobalIntegrations(),
    });

    const usedBy = (name: string): WorkspaceIntegration[] =>
        (integrations.data?.items ?? []).filter(i =>
            configRefersTo(i.configValues, name)
        );

    const refresh = () =>
        queryClient.invalidateQueries({queryKey: [secretsQueryKey]});

    const create = () => openModal(CreateSecretDialog, {workspaceId});

    const replace = (secret: WorkspaceSecret) =>
        openModal(ReplaceSecretValueDialog, {secret});

    const usedByText = (name: string) => {
        const users = usedBy(name);

        return users.length > 0
            ? t('secret.used_by', 'Used by {{integrations}}', {
                  integrations: users.map(integrationLabel).join(', '),
                  interpolation: {escapeValue: false},
              })
            : t('secret.unused', 'Not used');
    };

    const createVariable = () => openModal(CreateVariableDialog, {workspaceId});

    const editVariable = (variable: WorkspaceEnv) =>
        openModal(EditVariableDialog, {variable});

    const removeVariable = (variable: WorkspaceEnv) => {
        const users = usedBy(variable.name);

        return openModal(ConfirmDialog, {
            title: t('variable.delete.title', 'Delete the variable {{name}}?', {
                name: variable.name,
            }),
            destructive: true,
            confirmLabel: t('common.delete', 'Delete'),
            children:
                users.length > 0 ? (
                    <UsedByWarning
                        users={users}
                        fallback={t(
                            'variable.delete.fallback',
                            'Their reference will no longer resolve to its value.'
                        )}
                    />
                ) : undefined,
            onConfirm: async () => {
                await deleteWorkspaceEnv(variable.id);
                await queryClient.invalidateQueries({
                    queryKey: [envsQueryKey],
                });
                toast.success(t('variable.deleted', 'Variable deleted'));
            },
        });
    };

    const remove = (secret: WorkspaceSecret) => {
        const users = usedBy(secret.name);

        return openModal(ConfirmDialog, {
            title: t('secret.delete.title', 'Delete the secret {{name}}?', {
                name: secret.name,
            }),
            destructive: true,
            confirmLabel: t('common.delete', 'Delete'),
            children:
                users.length > 0 ? (
                    <UsedByWarning
                        users={users}
                        fallback={t(
                            'secret.delete.fallback',
                            'Their reference will then resolve to a variable of the same name, if any.'
                        )}
                    />
                ) : undefined,
            onConfirm: async () => {
                await deleteWorkspaceSecret(secret.id);
                await refresh();
                toast.success(t('secret.deleted', 'Secret deleted'));
            },
        });
    };

    return (
        <div className="space-y-6" data-testid="secret-manager">
            <section className="space-y-2">
                <div className="flex items-start gap-2">
                    <div className="min-w-0 flex-1">
                        <h3 className="flex items-center gap-1.5 text-sm font-medium">
                            <LockIcon className="size-4" />
                            {t('secret.title', 'Secrets')}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {workspaceId
                                ? t(
                                      'secret.intro',
                                      'Referenced as ${NAME} in the configuration of the integrations of this workspace. Their values are encrypted and never shown again.'
                                  )
                                : t(
                                      'secret.intro_instance',
                                      'Referenced as ${NAME} in the configuration of the instance integrations. Their values are encrypted and never shown again.'
                                  )}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        data-testid="secret-create"
                        onClick={() => void create()}
                    >
                        <PlusIcon /> {t('secret.create', 'New secret')}
                    </Button>
                </div>
                {loading ? (
                    <div className="space-y-1">
                        {[...Array(3)].map((_, i) => (
                            <Skeleton key={i} className="h-10" />
                        ))}
                    </div>
                ) : error && !secrets ? (
                    <LoadError error={error} className="py-6" />
                ) : secrets?.length ? (
                    <ul className="divide-y rounded-md border">
                        {secrets.map(secret => (
                            <li
                                key={secret.id}
                                className="flex items-center gap-2 px-3 py-2"
                                data-testid={`secret-${secret.name}`}
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-mono text-sm">
                                        {secret.name}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {usedByText(secret.name)}
                                    </div>
                                </div>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    data-testid={`secret-replace-${secret.name}`}
                                    onClick={() => void replace(secret)}
                                >
                                    <KeyRoundIcon />
                                    {t('secret.replace', 'Replace the value')}
                                </Button>
                                <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label={t('common.delete', 'Delete')}
                                    data-testid={`secret-delete-${secret.name}`}
                                    onClick={() => void remove(secret)}
                                >
                                    <Trash2Icon />
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        {t('secret.empty', 'No secret yet')}
                    </p>
                )}
            </section>
            <section className="space-y-2">
                <div className="flex items-start gap-2">
                    <div className="min-w-0 flex-1">
                        <h3 className="flex items-center gap-1.5 text-sm font-medium">
                            <BracesIcon className="size-4" />
                            {t('secret.variables', 'Variables')}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'secret.variables_help',
                                'Plain values referenced the same way, stored and shown in clear: never a sensitive value. A secret of the same name takes precedence.'
                            )}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        data-testid="variable-create"
                        onClick={() => void createVariable()}
                    >
                        <PlusIcon /> {t('variable.create', 'New variable')}
                    </Button>
                </div>
                {loading ? (
                    <div className="space-y-1">
                        {[...Array(2)].map((_, i) => (
                            <Skeleton key={i} className="h-10" />
                        ))}
                    </div>
                ) : envs?.length ? (
                    <ul className="divide-y rounded-md border">
                        {envs.map(variable => (
                            <li
                                key={variable.id}
                                className="flex items-center gap-2 px-3 py-2"
                                data-testid={`variable-${variable.name}`}
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex min-w-0 items-baseline gap-2 text-sm">
                                        <span className="shrink-0 font-mono">
                                            {variable.name}
                                        </span>
                                        <span className="truncate font-mono text-xs text-muted-foreground">
                                            = {variable.value}
                                        </span>
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {usedByText(variable.name)}
                                    </div>
                                </div>
                                <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label={t('common.edit', 'Edit')}
                                    data-testid={`variable-edit-${variable.name}`}
                                    onClick={() => void editVariable(variable)}
                                >
                                    <PencilIcon />
                                </Button>
                                <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label={t('common.delete', 'Delete')}
                                    data-testid={`variable-delete-${variable.name}`}
                                    onClick={() =>
                                        void removeVariable(variable)
                                    }
                                >
                                    <Trash2Icon />
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : !error ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        {t('variable.empty', 'No variable yet')}
                    </p>
                ) : null}
            </section>
        </div>
    );
}

function UsedByWarning({
    users,
    fallback,
}: {
    users: WorkspaceIntegration[];
    fallback: string;
}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-2 text-sm">
            <p>
                {t('secret.delete.used_by', 'These integrations refer to it:')}
            </p>
            <ul className="list-disc pl-5">
                {users.map(i => (
                    <li key={i.id}>{integrationLabel(i)}</li>
                ))}
            </ul>
            <p className="text-muted-foreground">{fallback}</p>
        </div>
    );
}
