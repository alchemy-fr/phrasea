'use client';

import {useCallback, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {FormErrors} from '@/types/api';
import {
    compileSchema,
    initialValues,
    normalizeValues,
    validateValues,
} from './schema';
import type {LiFormErrors, LiFormField, LiFormValues} from './types';

export type CompiledSchema =
    | {schema: LiFormField; error?: undefined}
    | {schema?: undefined; error: string};

export function safeCompileSchema(schema: LiFormField): CompiledSchema {
    try {
        return {schema: compileSchema(schema)};
    } catch (e) {
        return {error: e instanceof Error ? e.message : String(e)};
    }
}

/** The errors answered by the API, keyed like the client ones */
export function fromServerErrors(errors: FormErrors): LiFormErrors {
    const result: LiFormErrors = {};
    for (const [field, messages] of Object.entries(errors)) {
        result[field === '_form' ? '' : field] = messages;
    }

    return result;
}

function startValues(
    schema: LiFormField | undefined,
    initial: LiFormValues | undefined
): LiFormValues {
    return {
        ...(schema ? (initialValues(schema, 'object') as LiFormValues) : {}),
        ...initial,
    };
}

/**
 * State of a LiForm: its values (starting from the default values of the
 * schema, or from `initial`), its errors, and what to send to the API.
 */
export function useLiForm(
    schema: LiFormField | null | undefined,
    initial?: LiFormValues
) {
    const {t} = useTranslation();
    const compiled = useMemo<CompiledSchema | undefined>(
        () => (schema ? safeCompileSchema(schema) : undefined),
        [schema]
    );
    const [state, setState] = useState(() => ({
        schema: compiled?.schema,
        values: startValues(compiled?.schema, initial),
    }));
    const [errors, setErrors] = useState<LiFormErrors>({});

    // A new schema (live preview of the editor) starts from its own defaults
    let values = state.values;
    if (state.schema !== compiled?.schema) {
        values = startValues(compiled?.schema, undefined);
        setState({schema: compiled?.schema, values});
    }

    const setValues = useCallback(
        (next: LiFormValues) => setState(s => ({...s, values: next})),
        []
    );

    const reset = useCallback(() => {
        setState(s => ({...s, values: startValues(s.schema, undefined)}));
        setErrors({});
    }, []);

    const messages = useMemo(
        () => ({
            required: t('liform.error.required', 'This value is required.'),
            number: t('liform.error.number', 'This value should be a number.'),
            integer: t(
                'liform.error.integer',
                'This value should be an integer.'
            ),
        }),
        [t]
    );

    /** Checks the values; resolves to the data to send, or null when invalid */
    const validate = useCallback((): LiFormValues | null => {
        if (!compiled?.schema) {
            return {};
        }
        const found = validateValues(compiled.schema, values, messages);
        setErrors(found);
        if (Object.keys(found).length > 0) {
            return null;
        }

        return (normalizeValues(compiled.schema, values, 'object') ??
            {}) as LiFormValues;
    }, [compiled, values, messages]);

    return {
        schema: compiled?.schema,
        schemaError: compiled?.error,
        values,
        setValues,
        errors,
        setErrors,
        validate,
        reset,
    };
}
