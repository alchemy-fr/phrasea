'use client';

import {useCallback} from 'react';
import {FormRenderer} from '@/features/form-builder/render/FormRenderer';
import {fromServerErrors} from '@/features/form-builder/render/serverErrors';
import {useBuilderForm} from '@/features/form-builder/render/useBuilderForm';
import type {
    FieldOptionsLoader,
    FormDefinition,
    FormValues,
} from '@/features/form-builder/types';
import {getFormFieldOptions} from '@/lib/api/uploader';
import type {RequestOptions} from '@/lib/api/http';
import type {FormErrors} from '@/types/api';

/** The form of a target, and the errors the API answers for its values */
export function useUploadForm(
    form: FormDefinition | null | undefined,
    initial?: FormValues
) {
    const builderForm = useBuilderForm(form, initial);
    const {setErrors} = builderForm;

    const setServerErrors = useCallback(
        (errors: FormErrors) => setErrors(fromServerErrors(errors)),
        [setErrors]
    );

    return {...builderForm, setServerErrors};
}

export type UploadFormState = ReturnType<typeof useUploadForm>;

export function useTargetOptionsLoader(
    targetId: string,
    opts?: RequestOptions
): FieldOptionsLoader {
    return useCallback(
        (field, query) => getFormFieldOptions(targetId, field.key, query, opts),
        [targetId, opts]
    );
}

export function UploadFormBody({
    form,
    loadOptions,
    disabled,
}: {
    form: UploadFormState;
    loadOptions: FieldOptionsLoader;
    disabled?: boolean;
}) {
    if (!form.definition) {
        return null;
    }

    return (
        <FormRenderer
            definition={form.definition}
            values={form.values}
            onChange={form.setValues}
            errors={form.errors}
            loadOptions={loadOptions}
            disabled={disabled}
        />
    );
}
