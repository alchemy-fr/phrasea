'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    BracesIcon,
    KeyRoundIcon,
    LockIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {WorkspaceIntegration, WorkspaceSecret} from '@/types/api';
import {
    getGlobalIntegrations,
    getWorkspaceIntegrations,
} from '@/lib/api/integrations';
import {deleteWorkspaceSecret, secretsQueryKey} from '@/lib/api/secrets';
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
import {configRefersTo} from './secretRef';
import {secretScopeKey, useSecretNames} from './useSecretNames';

type Props = {
    /** Omitted for the secrets of the instance (administrators only) */
    workspaceId?: string;
};

/**
 * The secrets of a workspace (or of the instance): their names, the
 * integrations referring to them, their values replaced but never shown.
 * The variables, managed in the admin, are only listed.
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
                    <div className="space-y-2 text-sm">
                        <p>
                            {t(
                                'secret.delete.used_by',
                                'These integrations refer to it:'
                            )}
                        </p>
                        <ul className="list-disc pl-5">
                            {users.map(i => (
                                <li key={i.id}>{integrationLabel(i)}</li>
                            ))}
                        </ul>
                        <p className="text-muted-foreground">
                            {t(
                                'secret.delete.fallback',
                                'Their reference will then resolve to a variable of the same name, the environment of the instance (PWI_…), or nothing.'
                            )}
                        </p>
                    </div>
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
                        {secrets.map(secret => {
                            const users = usedBy(secret.name);

                            return (
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
                                            {users.length > 0
                                                ? t(
                                                      'secret.used_by',
                                                      'Used by {{integrations}}',
                                                      {
                                                          integrations: users
                                                              .map(
                                                                  integrationLabel
                                                              )
                                                              .join(', '),
                                                          interpolation: {
                                                              escapeValue: false,
                                                          },
                                                      }
                                                  )
                                                : t(
                                                      'secret.unused',
                                                      'Not used'
                                                  )}
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        data-testid={`secret-replace-${secret.name}`}
                                        onClick={() => void replace(secret)}
                                    >
                                        <KeyRoundIcon />
                                        {t(
                                            'secret.replace',
                                            'Replace the value'
                                        )}
                                    </Button>
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        aria-label={t(
                                            'common.delete',
                                            'Delete'
                                        )}
                                        data-testid={`secret-delete-${secret.name}`}
                                        onClick={() => void remove(secret)}
                                    >
                                        <Trash2Icon />
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        {t('secret.empty', 'No secret yet')}
                    </p>
                )}
            </section>
            {envs?.length ? (
                <section className="space-y-2">
                    <div>
                        <h3 className="flex items-center gap-1.5 text-sm font-medium">
                            <BracesIcon className="size-4" />
                            {t('secret.variables', 'Variables')}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'secret.variables_help',
                                'Plain values managed by an administrator, referenced the same way. A secret of the same name takes precedence.'
                            )}
                        </p>
                    </div>
                    <ul className="flex flex-wrap gap-1.5">
                        {envs.map(env => (
                            <li
                                key={env.id}
                                className="rounded-md border px-2 py-0.5 font-mono text-xs"
                            >
                                {env.name}
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}
