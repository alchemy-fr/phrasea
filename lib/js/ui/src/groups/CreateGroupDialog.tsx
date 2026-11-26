'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {PlusIcon} from 'lucide-react';
import {toast} from 'sonner';
import {FormDialog} from '../modals/FormDialog';
import type {ModalProps} from '../modals/ModalProvider';
import {FormRow, Input} from '../ui/input';
import {isApiError} from '../utils/errors';
import {
    groupPickerQueryKey,
    groupsQueryKey,
    type GroupsApi,
    type KeycloakGroup,
} from './types';

/**
 * The refusal of a group name (taken, empty, too long), as the API reports it
 * on the `name` field. Anything else is not about the name.
 */
export function getNameError(e: unknown): string | undefined {
    if (!isApiError(e) || ![400, 409, 422].includes(e.status)) {
        return undefined;
    }
    const messages = e.violations
        .filter(v => v.propertyPath === 'name')
        .map(v => v.message);

    return messages.length > 0 ? messages.join('\n') : undefined;
}

/** Makes every list of groups stale: the managed ones and the pickers */
export function invalidateGroups(
    queryClient: ReturnType<typeof useQueryClient>
): void {
    void queryClient.invalidateQueries({queryKey: groupsQueryKey});
    void queryClient.invalidateQueries({queryKey: groupPickerQueryKey});
}

type Props = ModalProps<KeycloakGroup> & {
    api: GroupsApi;
    /** Name typed beforehand (e.g. the search of a group picker) */
    initialName?: string;
};

/**
 * Creates a top level Keycloak group, resolving it.
 */
export function CreateGroupDialog({
    open,
    onOpenChange,
    resolve,
    api,
    initialName,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [name, setName] = useState(initialName ?? '');
    const [error, setError] = useState<string>();

    const submit = async () => {
        let created: KeycloakGroup;
        try {
            created = await api.createGroup(name.trim());
        } catch (e) {
            const nameError = getNameError(e);
            if (nameError) {
                setError(nameError);

                return false;
            }
            throw e;
        }
        invalidateGroups(queryClient);
        toast.success(t('groups.created', 'Group created'));
        resolve?.(created);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('groups.create_title', 'New group')}
            submitLabel={t('groups.create', 'Create')}
            submitIcon={<PlusIcon />}
            canSubmit={!!name.trim()}
            onSubmit={submit}
        >
            <FormRow
                label={t('groups.name', 'Name')}
                htmlFor="create-group-name"
                error={error}
                className="mb-0"
            >
                <Input
                    id="create-group-name"
                    data-testid="create-group-name"
                    autoFocus
                    autoComplete="off"
                    maxLength={255}
                    value={name}
                    aria-invalid={!!error}
                    onChange={e => {
                        setName(e.target.value);
                        setError(undefined);
                    }}
                />
            </FormRow>
        </FormDialog>
    );
}
