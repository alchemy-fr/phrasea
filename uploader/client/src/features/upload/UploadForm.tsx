'use client';

import {useCallback} from 'react';
import {LiFormBody} from '@/features/liform/LiFormBody';
import {fromServerErrors, useLiForm} from '@/features/liform/useLiForm';
import {FormRenderer} from '@/features/form-builder/render/FormRenderer';
import {useBuilderForm} from '@/features/form-builder/render/useBuilderForm';
import type {
    FieldOptionsLoader,
    FormValues,
} from '@/features/form-builder/types';
import {getFormFieldOptions} from '@/lib/api/uploader';
import {isBuilderSchema, type FormErrors, type FormSchema} from '@/types/api';

/**
 * The form of a target, whatever its format: a LiForm schema or a form of
 * the builder.
 */
export function useUploadForm(
    schema: FormSchema | null | undefined,
    initial?: FormValues
) {
    const builder = isBuilderSchema(schema);
    const liForm = useLiForm(
        builder ? null : schema?.data,
        builder ? undefined : initial
    );
    const builderForm = useBuilderForm(
        builder ? schema.data : null,
        builder ? initial : undefined
    );
    const {setErrors: setLiFormErrors} = liForm;
    const {setErrors: setBuilderErrors} = builderForm;

    const setServerErrors = useCallback(
        (errors: FormErrors) =>
            (builder ? setBuilderErrors : setLiFormErrors)(
                fromServerErrors(errors)
            ),
        [builder, setBuilderErrors, setLiFormErrors]
    );

    const active = builder ? builderForm : liForm;

    return {
        builder,
        liForm,
        builderForm,
        values: active.values,
        validate: active.validate,
        reset: active.reset,
        setServerErrors,
    };
}

export type UploadFormState = ReturnType<typeof useUploadForm>;

export function useTargetOptionsLoader(targetId: string): FieldOptionsLoader {
    return useCallback(
        (field, query) => getFormFieldOptions(targetId, field.key, query),
        [targetId]
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
    if (!form.builder) {
        return <LiFormBody form={form.liForm} disabled={disabled} />;
    }
    const {builderForm} = form;
    if (!builderForm.definition) {
        return null;
    }

    return (
        <FormRenderer
            definition={builderForm.definition}
            values={builderForm.values}
            onChange={builderForm.setValues}
            errors={builderForm.errors}
            loadOptions={loadOptions}
            disabled={disabled}
        />
    );
}
