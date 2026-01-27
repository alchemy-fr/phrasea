import type {Translations} from '@alchemy/i18n';
import {DatePickerProps as DatePickerPropsBase} from 'react-datepicker';
import React from 'react';

export type Translation = {
    locale: string;
    value: string;
};

export type WithTranslations = {
    id: string;
    translations?: Translations | undefined;
};

export type DatePickerProps = {
    time?: boolean;
    error?: boolean;
    dateFormat?: string;
    timeFormat?: string;
    onChange: (date: string | null) => void;
    value: Date | null;
    inputRef: React.Ref<HTMLInputElement>;
} & Omit<DatePickerPropsBase, 'onChange' | 'selected' | 'customInput'>;
