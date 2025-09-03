import React from 'react';
import {FieldValues} from 'react-hook-form';
import {RSelectProps, SelectOption} from '../RSelectWidget';
import AsyncRSelectWidget from "../AsyncRSelectWidget";

export type GetLocales = () => Promise<SelectOption[]>

type Props<TFieldValues extends FieldValues> = {
    getLocales: GetLocales;
} & RSelectProps<
    TFieldValues,
    false
>;

export default function LocaleSelectWidget<TFieldValues extends FieldValues>(
    {getLocales, ...props}: Props<TFieldValues>
) {
    return <AsyncRSelectWidget
        {...props}
        loadOptions={() => getLocales()}
    />;
}
