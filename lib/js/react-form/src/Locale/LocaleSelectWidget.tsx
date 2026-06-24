import React from 'react';
import {FieldValues} from 'react-hook-form';
import AsyncRSelectWidget, {AsyncRSelectProps} from '../AsyncRSelectWidget';
import {SelectOption} from '../types';

export type GetLocales = () => Promise<SelectOption[]>;

type Props<TFieldValues extends FieldValues> = {
    getLocales: GetLocales;
    filteredValues?: string[] | undefined;
} & AsyncRSelectProps<TFieldValues, false>;

export default function LocaleSelectWidget<TFieldValues extends FieldValues>({
    getLocales,
    filteredValues,
    ...props
}: Props<TFieldValues>) {
    const load = async (
        inputValue?: string | undefined
    ): Promise<SelectOption[]> => {
        const result = await getLocales();
        const searchString = (inputValue || '').toLowerCase();

        return result
            .filter(i => i.label.toLowerCase().includes(searchString))
            .filter(i => !filteredValues || filteredValues.includes(i.value));
    };

    return <AsyncRSelectWidget {...props} loadOptions={load} />;
}
