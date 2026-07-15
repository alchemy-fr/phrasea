import React from 'react';
import {FieldValues} from 'react-hook-form';
import AsyncRSelectWidget from '../RSelect/AsyncRSelectWidget';
import {AsyncRSelectProps, SelectOption} from '../types';

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
    const loadOptions = async (inputValue?: string | undefined) => {
        const result = await getLocales();
        const searchString = (inputValue || '').toLowerCase();

        const options: SelectOption[] = result
            .filter(i => i.label.toLowerCase().includes(searchString))
            .filter(i => !filteredValues || filteredValues.includes(i.value));

        return {
            options,
            hasMore: false,
            additional: {},
        };
    };

    return <AsyncRSelectWidget {...props} loadOptions={loadOptions} />;
}
