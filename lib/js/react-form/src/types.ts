import type {Translations} from '@alchemy/i18n';
import {DatePickerProps as DatePickerPropsBase} from 'react-datepicker';
import React, {ReactNode} from 'react';
import {Control, FieldPath, FieldValues} from 'react-hook-form';
import {ActionMeta, CommonProps, OnChangeValue} from 'react-select';
import {CreatableProps} from 'react-select/creatable';
import {GroupBase} from './RSelectWidget';

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

export type ResolvedChangedValue<
    ResolveValue extends boolean,
    IsMulti extends boolean = false,
    Opt extends SelectOption = SelectOption,
> = ResolveValue extends true
    ? IsMulti extends true
        ? Opt['value'][]
        : Opt['value'] | null
    : IsMulti extends true
      ? Opt[]
      : Opt | null;

export type SelectOption = Readonly<{
    label: string;
    value: string;
    image?: React.ElementType | React.FC;
    item?: object | undefined;
}>;

export type RSelectProps<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
    IsAllowCreate extends boolean = false,
    Opt extends SelectOption = SelectOption,
    Normalized = any,
> = (
    | {
          control: Control<TFieldValues>;
          name: FieldPath<TFieldValues>;
          onChange?: (
              newValue: OnChangeValue<
                  ResolvedChangedValue<true, IsMulti, Opt>,
                  IsMulti
              >,
              actionMeta: ActionMeta<Opt>
          ) => void;
      }
    | {
          control?: never;
          name?: string;
          onChange?: (
              newValue: OnChangeValue<
                  ResolvedChangedValue<
                      IsAllowCreate extends true ? true : false,
                      IsMulti,
                      Opt
                  >,
                  IsMulti
              >,
              actionMeta: ActionMeta<SelectOption>
          ) => void;
      }
) & {
    error?: boolean | undefined;
    clearOnSelect?: boolean;
    disabled?: boolean | undefined;
    label?: ReactNode;
    allowCreate?: IsAllowCreate;
    inputHeight?: number;
    menuWidth?: number;
    creatableProps?: Partial<CreatableProps<Opt, IsMulti, GroupBase<Opt>>>;
    denormalizeValue?: SelectDenormalizeValue<Opt, Normalized>;
    normalizeValue?: SelectNormalizeValue<Opt, Normalized>;
} & Partial<
        Omit<
            CommonProps<Opt, IsMulti, GroupBase<Opt>>['selectProps'],
            'onChange'
        >
    >;

export type SelectDenormalizeValue<
    Opt extends SelectOption = SelectOption,
    Normalized = any,
> = (value: Opt['value'] | null) => Normalized | null;

export type SelectNormalizeValue<
    Opt extends SelectOption = SelectOption,
    Normalized = any,
> = (value: Normalized | null) => Opt['value'] | null;
