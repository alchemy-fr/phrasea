'use client';

import {Alert} from '@/components/ui/misc';
import {LiFormFields} from './LiForm';
import type {useLiForm} from './useLiForm';

/** The fields of a LiForm state, or why its schema cannot be rendered */
export function LiFormBody({
    form,
    disabled,
}: {
    form: ReturnType<typeof useLiForm>;
    disabled?: boolean;
}) {
    if (form.schemaError) {
        return <Alert variant="destructive">{form.schemaError}</Alert>;
    }
    if (!form.schema) {
        return null;
    }

    return (
        <LiFormFields
            schema={form.schema}
            value={form.values}
            onChange={form.setValues}
            errors={form.errors}
            disabled={disabled}
        />
    );
}
