'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {EyeIcon, EyeOffIcon, LockIcon, SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {WorkspaceSecret} from '@/types/api';
import {isApiError} from '@/lib/api/http';
import {
    postWorkspaceSecret,
    putWorkspaceSecret,
    SecretScope,
    secretsQueryKey,
} from '@/lib/api/secrets';
import {
    isValidSecretName,
    secretNameMaxLength,
} from '@/features/secrets/secretRef';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {Button} from '@alchemy/next-ui/ui/button';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';

/** A password field whose value can be shown */
export function SecretValueInput({
    id,
    value,
    onChange,
    placeholder,
    autoFocus,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    autoFocus?: boolean;
}) {
    const {t} = useTranslation();
    const [shown, setShown] = useState(false);

    return (
        <div className="flex items-center gap-1">
            <Input
                id={id}
                type={shown ? 'text' : 'password'}
                value={value}
                onChange={e => onChange(e.target.value)}
                placeholder={placeholder}
                autoComplete="new-password"
                autoFocus={autoFocus}
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={
                    shown
                        ? t('integration.config.hide_secret', 'Hide')
                        : t('integration.config.show_secret', 'Show')
                }
                aria-pressed={shown}
                onClick={() => setShown(s => !s)}
            >
                {shown ? <EyeOffIcon /> : <EyeIcon />}
            </Button>
        </div>
    );
}

function ScopeRow({workspaceId}: SecretScope) {
    const {t} = useTranslation();

    return (
        <p className="mb-4 flex items-center gap-1.5 text-xs text-muted-foreground">
            <LockIcon className="size-3.5" />
            {workspaceId
                ? t(
                      'secret.scope_workspace',
                      'Secret of this workspace, usable by its integrations. Its value is never shown again.'
                  )
                : t(
                      'secret.scope_instance',
                      'Secret of the instance, usable by the instance integrations. Its value is never shown again.'
                  )}
        </p>
    );
}

type Props = ModalProps<WorkspaceSecret | undefined> &
    SecretScope & {
        name?: string;
        value?: string;
    };

/**
 * Creates a secret of the workspace (of the instance without one), to be
 * referenced as `${NAME}` by the configuration of integrations.
 */
export function CreateSecretDialog({
    open,
    onOpenChange,
    resolve,
    workspaceId,
    name: initialName,
    value: initialValue,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [name, setName] = useState(initialName ?? '');
    const [value, setValue] = useState(initialValue ?? '');
    const [nameError, setNameError] = useState<string>();
    const trimmed = name.trim();
    const invalidName = trimmed !== '' && !isValidSecretName(trimmed);

    const submit = async () => {
        let created: WorkspaceSecret;
        try {
            created = await postWorkspaceSecret({
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
        void queryClient.invalidateQueries({queryKey: [secretsQueryKey]});
        toast.success(t('secret.created', 'Secret created'));
        resolve?.(created);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('secret.create_title', 'New secret')}
            submitLabel={t('secret.create_submit', 'Create')}
            submitIcon={<LockIcon />}
            canSubmit={trimmed !== '' && !invalidName && value !== ''}
            dirty={value !== (initialValue ?? '')}
            onSubmit={submit}
        >
            <ScopeRow workspaceId={workspaceId} />
            <FormRow
                label={t('secret.name', 'Name')}
                htmlFor="create-secret-name"
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
                    id="create-secret-name"
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
                label={t('secret.value', 'Value')}
                htmlFor="create-secret-value"
            >
                <SecretValueInput
                    id="create-secret-value"
                    value={value}
                    onChange={setValue}
                    autoFocus={!!initialName && !initialValue}
                />
            </FormRow>
        </FormDialog>
    );
}

/**
 * Replaces the value of a secret, which is never shown: its name cannot
 * change (the references to it would break).
 */
export function ReplaceSecretValueDialog({
    open,
    onOpenChange,
    resolve,
    secret,
}: ModalProps<WorkspaceSecret | undefined> & {secret: WorkspaceSecret}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [value, setValue] = useState('');

    const submit = async () => {
        const saved = await putWorkspaceSecret(secret.id, {value});
        void queryClient.invalidateQueries({queryKey: [secretsQueryKey]});
        toast.success(t('secret.saved', 'Secret saved'));
        resolve?.(saved);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('secret.edit_title', 'Replace the value of the secret')}
            submitLabel={t('common.save', 'Save')}
            submitIcon={<SaveIcon />}
            canSubmit={value !== ''}
            dirty={value !== ''}
            onSubmit={submit}
        >
            <FormRow
                label={t('secret.name', 'Name')}
                htmlFor="edit-secret-name"
            >
                <Input
                    id="edit-secret-name"
                    value={secret.name}
                    readOnly
                    className="font-mono"
                />
            </FormRow>
            <FormRow
                label={t('secret.new_value', 'New value')}
                htmlFor="edit-secret-value"
            >
                <SecretValueInput
                    id="edit-secret-value"
                    value={value}
                    onChange={setValue}
                    placeholder={t('secret.value_unchanged', 'Unchanged')}
                    autoFocus
                />
            </FormRow>
        </FormDialog>
    );
}
