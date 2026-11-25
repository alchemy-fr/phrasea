'use client';

import {useState} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {FieldError} from '@alchemy/next-ui/ui/input';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {isApiError} from '@alchemy/next-ui/utils/errors';
import {createTarget} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {Target} from '@/types/api';
import {
    EMPTY_TARGET_VALUES,
    TargetPropertiesFields,
    type TargetValues,
    toTargetInput,
} from './TargetPropertiesFields';
import {violationsByField} from './violations';

/**
 * Creates a target; who may upload to it is set by its allowed groups (none
 * for everyone).
 */
export function TargetCreateDialog({
    open,
    onOpenChange,
    resolve,
}: ModalProps<Target>) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [values, setValues] = useState<TargetValues>(EMPTY_TARGET_VALUES);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const set = (patch: Partial<TargetValues>) =>
        setValues(v => ({...v, ...patch}));

    const submit = async () => {
        setErrors({});
        try {
            const created = await createTarget(toTargetInput(values));
            await queryClient.invalidateQueries({queryKey: targetsQueryKey});
            resolve?.(created);

            return false;
        } catch (e) {
            if (isApiError(e, 422)) {
                setErrors(violationsByField(e));

                return false;
            }
            throw e;
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('admin.target_create.title', 'New target')}
            submitLabel={t('admin.target_create.submit', 'Create the target')}
            canSubmit={!!values.name.trim()}
            dirty={values !== EMPTY_TARGET_VALUES}
            onSubmit={submit}
        >
            <div data-testid="target-create-form">
                <TargetPropertiesFields
                    values={values}
                    errors={errors}
                    set={set}
                    autoFocus
                />
                {errors[''] ? <FieldError>{errors['']}</FieldError> : null}
            </div>
        </FormDialog>
    );
}
