'use client';

import {useCallback, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {initialValues, normalizeValues, validateValues} from '../definition';
import type {FormDefinition, FormFieldErrors, FormValues} from '../types';

/**
 * State of a builder form: its values (from the default values of its fields,
 * or from `initial`), its errors, and what to send to the API.
 */
export function useBuilderForm(
    definition: FormDefinition | null | undefined,
    initial?: FormValues
) {
    const {t} = useTranslation();
    const [state, setState] = useState(() => ({
        definition,
        values: definition ? initialValues(definition, initial) : {},
    }));
    const [errors, setErrors] = useState<FormFieldErrors>({});

    // A new definition (live preview of the builder) keeps the values of the
    // fields still there
    let values = state.values;
    if (state.definition !== definition) {
        values = definition ? initialValues(definition, values) : {};
        setState({definition, values});
    }

    const setValues = useCallback(
        (next: FormValues) => setState(s => ({...s, values: next})),
        []
    );

    const reset = useCallback(() => {
        setState(s => ({
            ...s,
            values: s.definition ? initialValues(s.definition) : {},
        }));
        setErrors({});
    }, []);

    const messages = useMemo(
        () => ({
            required: t('form.error.required', 'This value is required.'),
            number: t('form.error.number', 'This value should be a number.'),
            minLength: (n: number) =>
                t('form.error.min_length', 'At least {{n}} characters.', {n}),
            maxLength: (n: number) =>
                t('form.error.max_length', 'At most {{n}} characters.', {n}),
            min: (n: number) =>
                t('form.error.min', 'This value should be {{n}} or more.', {n}),
            max: (n: number) =>
                t('form.error.max', 'This value should be {{n}} or less.', {
                    n,
                }),
        }),
        [t]
    );

    /** Checks the values; returns the data to send, or null when invalid */
    const validate = useCallback((): FormValues | null => {
        if (!definition) {
            return {};
        }
        const found = validateValues(definition, values, messages);
        setErrors(found);
        if (Object.keys(found).length > 0) {
            return null;
        }

        return normalizeValues(definition, values);
    }, [definition, values, messages]);

    return {
        definition,
        values,
        setValues,
        errors,
        setErrors,
        validate,
        reset,
    };
}

export type BuilderFormState = ReturnType<typeof useBuilderForm>;
