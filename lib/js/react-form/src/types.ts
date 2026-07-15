import type {Translations} from '@alchemy/i18n';
import {DatePickerProps as DatePickerPropsBase} from 'react-datepicker';
import React, {ReactElement, ReactNode} from 'react';
import {Control, FieldPath, FieldValues} from 'react-hook-form';
import {ActionMeta, CommonProps, OnChangeValue} from 'react-select';
import {CreatableProps} from 'react-select/creatable';
import {
    ComponentProps,
    LoadOptions,
    UseAsyncPaginateParams,
} from 'react-select-async-paginate';
import {AsyncProps} from 'react-select/async';

export type Translation = {
    locale: string;
    value: string;
};

export enum ClassName {
    OptionImage = 'rselect-img',
}

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

export interface GroupBase<Option> {
    readonly options: readonly Option[];
    readonly label?: string;
}

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

export type RSelectOnCreate<Opt extends SelectOption> = (
    inputValue: string,
    onCreate: (option: Opt) => void
) => void;

export type SelectLoadOptions<Opt extends SelectOption = SelectOption> =
    LoadOptions<Opt, GroupBase<Opt>, SelectPaginationData>;

export type CompositeOption<
    IsMulti extends boolean,
    Opt extends SelectOption,
> = IsMulti extends true ? Opt[] : Opt | null;

export type CompositeValue<IsMulti extends boolean> = IsMulti extends true
    ? string[]
    : string | undefined;

export type SelectPaginationData = {
    nextUrl?: string;
};
export type AsyncPaginateCreatableProps<
    OptionType,
    IsMulti extends boolean,
    Group extends GroupBase<OptionType> = GroupBase<OptionType>,
> = Omit<CreatableProps<OptionType, IsMulti, Group>, 'loadOptions'> &
    UseAsyncPaginateParams<OptionType, Group, SelectPaginationData> &
    ComponentProps<OptionType, Group, IsMulti>;
export type AsyncPaginateCreatableType = <
    OptionType,
    IsMulti extends boolean = false,
    Group extends GroupBase<OptionType> = GroupBase<OptionType>,
>(
    props: AsyncPaginateCreatableProps<OptionType, IsMulti, Group>
) => ReactElement;
type AsyncPaginateProps<
    OptionType,
    IsMulti extends boolean,
    Group extends GroupBase<OptionType> = GroupBase<OptionType>,
> = Omit<AsyncProps<OptionType, IsMulti, Group>, 'loadOptions'> &
    UseAsyncPaginateParams<OptionType, Group, SelectPaginationData> &
    ComponentProps<OptionType, Group, IsMulti>;
export type AsyncPaginateType = <
    OptionType,
    IsMulti extends boolean = false,
    Group extends GroupBase<OptionType> = GroupBase<OptionType>,
>(
    props: AsyncPaginateProps<OptionType, IsMulti, Group>
) => ReactElement;

export type AsyncRSelectProps<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
    Opt extends SelectOption = SelectOption,
> = (
    | {
          control: Control<TFieldValues>;
          name: FieldPath<TFieldValues>;
      }
    | {
          control?: never;
          name?: string;
      }
) & {
    error?: boolean | undefined;
    cacheId?: string;
    disabledValues?: string[];
    clearOnSelect?: boolean;
    disabled?: boolean | undefined;
    cacheOptions?: any;
    onCreate?: RSelectOnCreate<Opt>;
    label?: ReactNode;
    inputHeight?: number;
    menuWidth?: number;
    loadOptions?: AsyncPaginateCreatableProps<
        Opt,
        IsMulti,
        GroupBase<Opt>
    >['loadOptions'];
} & Omit<
        AsyncPaginateCreatableProps<Opt, IsMulti, GroupBase<Opt>>,
        'loadOptions'
    >;
