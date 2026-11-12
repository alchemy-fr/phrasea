'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {BracesIcon, SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {WorkspaceEnv} from '@/types/api';
import {isApiError} from '@/lib/api/http';
import {
    envsQueryKey,
    postWorkspaceEnv,
    putWorkspaceEnv,
    SecretScope,
} from '@/lib/api/secrets';
import {
    isValidSecretName,
    secretNameMaxLength,
} from '@/features/secrets/secretRef';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';

function ScopeRow({workspaceId}: SecretScope) {
    const {t} = useTranslation();

    return (
        <p className="mb-4 flex items-center gap-1.5 text-xs text-muted-foreground">
            <BracesIcon className="size-3.5" />
            {workspaceId
                ? t(
                      'variable.scope_workspace',
                      'Variable of this workspace, usable by its integrations. Stored in clear: use a secret for a sensitive value.'
                  )
                : t(
                      'variable.scope_instance',
                      'Variable of the instance, usable by the instance integrations. Stored in clear: use a secret for a sensitive value.'
                  )}
        </p>
    );
}

type Props = ModalProps<WorkspaceEnv | undefined> &
    SecretScope & {
        name?: string;
    };

/**
 * Creates a variable of the workspace (of the instance without one), to be
 * referenced as `${NAME}` by the configuration of integrations.
 */
export function CreateVariableDialog({
    open,
    onOpenChange,
    resolve,
    workspaceId,
    name: initialName,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [name, setName] = useState(initialName ?? '');
    const [value, setValue] = useState('');
    const [nameError, setNameError] = useState<string>();
    const trimmed = name.trim();
    const invalidName = trimmed !== '' && !isValidSecretName(trimmed);

    const submit = async () => {
        let created: WorkspaceEnv;
        try {
            created = await postWorkspaceEnv({
                workspaceId,
                name: trimmed,
                value,
            });
        } catch (e) {
            if (isApiError(e, 422)) {
                const messages = e.violations
                    .filter(v => v.propertyPath === 'name')
                    .map(v => v.message);
                if (messages.length > 0) {
                    setNameError(messages.join('\n'));

                    return false;
                }
            }
            throw e;
        }
        void queryClient.invalidateQueries({queryKey: [envsQueryKey]});
        toast.success(t('variable.created', 'Variable created'));
        resolve?.(created);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('variable.create_title', 'New variable')}
            submitLabel={t('variable.create_submit', 'Create')}
            submitIcon={<BracesIcon />}
            canSubmit={trimmed !== '' && !invalidName}
            dirty={value !== ''}
            onSubmit={submit}
        >
            <ScopeRow workspaceId={workspaceId} />
            <FormRow
                label={t('variable.name', 'Name')}
                htmlFor="create-variable-name"
                error={
                    nameError ??
                    (invalidName
                        ? t(
                              'secret.name_invalid',
                              'Letters, digits and "_" only, not starting with a digit'
                          )
                        : undefined)
                }
                help={t(
                    'secret.name_help',
                    'Referenced as ${NAME} in the configuration; it cannot be changed afterwards.'
                )}
            >
                <Input
                    id="create-variable-name"
                    autoFocus={!initialName}
                    value={name}
                    maxLength={secretNameMaxLength}
                    onChange={e => {
                        setName(e.target.value);
                        setNameError(undefined);
                    }}
                    aria-invalid={!!nameError || invalidName}
                    autoComplete="off"
                    className="font-mono"
                />
            </FormRow>
            <FormRow
                label={t('variable.value', 'Value')}
                htmlFor="create-variable-value"
            >
                <Input
                    id="create-variable-value"
                    value={value}
                    onChange={e => setValue(e.target.value)}
                    autoComplete="off"
                    autoFocus={!!initialName}
                />
            </FormRow>
        </FormDialog>
    );
}

/** Changes the value of a variable: its name cannot change */
export function EditVariableDialog({
    open,
    onOpenChange,
    resolve,
    variable,
}: ModalProps<WorkspaceEnv | undefined> & {variable: WorkspaceEnv}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [value, setValue] = useState(variable.value);

    const submit = async () => {
        const saved = await putWorkspaceEnv(variable.id, {value});
        void queryClient.invalidateQueries({queryKey: [envsQueryKey]});
        toast.success(t('variable.saved', 'Variable saved'));
        resolve?.(saved);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('variable.edit_title', 'Edit the variable')}
            submitLabel={t('common.save', 'Save')}
            submitIcon={<SaveIcon />}
            canSubmit={value !== variable.value}
            dirty={value !== variable.value}
            onSubmit={submit}
        >
            <FormRow
                label={t('variable.name', 'Name')}
                htmlFor="edit-variable-name"
            >
                <Input
                    id="edit-variable-name"
                    value={variable.name}
                    readOnly
                    className="font-mono"
                />
            </FormRow>
            <FormRow
                label={t('variable.value', 'Value')}
                htmlFor="edit-variable-value"
            >
                <Input
                    id="edit-variable-value"
                    value={value}
                    onChange={e => setValue(e.target.value)}
                    autoComplete="off"
                    autoFocus
                />
            </FormRow>
        </FormDialog>
    );
}
