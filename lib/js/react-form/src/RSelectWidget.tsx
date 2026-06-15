import {Controller, FieldValues} from 'react-hook-form';
import React, {useEffect, useState} from 'react';
import {GlobalStyles, InputLabel, useTheme} from '@mui/material';
import Select, {components, OptionProps} from 'react-select';
import {createSelectStyles} from './selectStyles';
import {valueToOption} from './AsyncRSelectWidget';
import CreatableSelect from 'react-select/creatable';
import {
    ResolvedChangedValue,
    RSelectProps,
    SelectDenormalizeValue,
    SelectOption,
} from './types';

export interface GroupBase<Option> {
    readonly options: readonly Option[];
    readonly label?: string;
}

export const rSelectClassName = 'rselect-img';

export function ImageOption<Opt extends SelectOption>(props: OptionProps<Opt>) {
    return (
        <components.Option {...props}>
            {props.data.image && (
                <span className={rSelectClassName}>
                    {React.createElement(props.data.image, {
                        fontSize: 'small',
                    })}
                </span>
            )}
            {props.data.label}
        </components.Option>
    );
}

const componentsProp = {
    Option: ImageOption,
};

export default function RSelectWidget<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
    IsAllowCreate extends boolean = false,
    Opt extends SelectOption = SelectOption,
    Normalized = any,
>({
    control,
    name,
    options,
    value: initialValue,
    clearOnSelect,
    onChange: onChangeProp,
    error,
    isMulti,
    required,
    label,
    styles,
    inputHeight,
    menuWidth = 300,
    allowCreate,
    creatableProps = {},
    normalizeValue,
    denormalizeValue,
    ...rest
}: RSelectProps<TFieldValues, IsMulti, IsAllowCreate, Opt, Normalized>) {
    const [proxyValue, setValue] = useState(initialValue);
    const theme = useTheme();

    const value = normalizeValue
        ? isMulti
            ? proxyValue
                ? (proxyValue as any[]).map(normalizeValue)
                : proxyValue
            : normalizeValue(proxyValue as any)
        : proxyValue;

    useEffect(() => {
        setValue(initialValue);
    }, [initialValue]);

    const indexedOptions = React.useMemo(() => {
        const index: Record<string, Opt> = {};
        (options as Opt[] | undefined)?.forEach(o => {
            index[o.value] = o;
        });

        return index;
    }, [options]);

    const Component = allowCreate ? CreatableSelect : Select;

    if (control) {
        return (
            <Controller
                control={control}
                name={name}
                rules={{
                    required,
                }}
                render={({field: {onChange, value: proxyValue, ref}}) => {
                    const value = normalizeValue
                        ? normalizeValue(proxyValue)
                        : proxyValue;

                    return (
                        <>
                            {label ? <InputLabel>{label}</InputLabel> : ''}
                            <RSelectStyle />
                            <Component<Opt, any>
                                {...rest}
                                {...creatableProps}
                                options={options}
                                ref={ref}
                                required={required}
                                components={componentsProp}
                                value={
                                    allowCreate
                                        ? value
                                        : (valueToOption(
                                              isMulti || false,
                                              value || undefined,
                                              indexedOptions
                                          ) as any)
                                }
                                onChange={(newValue, meta) => {
                                    const denormalizedValue = resolveValues<
                                        true,
                                        IsMulti,
                                        Opt
                                    >({
                                        handler: denormalizeValue,
                                        isMulti,
                                        resolveValue: true,
                                        newValue: newValue as any,
                                    });
                                    onChange(denormalizedValue);
                                    onChangeProp?.(
                                        denormalizedValue as any,
                                        meta
                                    );
                                }}
                                isClearable={!required}
                                isMulti={isMulti}
                                menuPortalTarget={document.body}
                                styles={createSelectStyles(
                                    theme,
                                    error,
                                    styles,
                                    inputHeight,
                                    menuWidth
                                )}
                            />
                        </>
                    );
                }}
            />
        );
    }

    return (
        <>
            {label ? <InputLabel>{label}</InputLabel> : ''}
            <RSelectStyle />
            <Component<Opt, IsMulti>
                {...rest}
                options={options}
                required={required}
                components={componentsProp}
                onChange={(newValue, meta) => {
                    const denormalizedValue = resolveValues<
                        false,
                        IsMulti,
                        Opt
                    >({
                        handler: denormalizeValue,
                        isMulti,
                        resolveValue: false,
                        newValue: newValue as any,
                    });
                    onChangeProp?.(denormalizedValue as any, meta);
                    setValue(!clearOnSelect ? denormalizedValue : null);
                }}
                value={valueToOption(
                    isMulti || false,
                    value as any,
                    indexedOptions
                )}
                isClearable={!required}
                isMulti={isMulti}
                menuPortalTarget={document.body}
                styles={createSelectStyles(
                    theme,
                    error,
                    styles,
                    inputHeight,
                    menuWidth
                )}
            />
        </>
    );
}

function resolveValues<
    ResolveValue extends boolean,
    IsMulti extends boolean = false,
    Opt extends SelectOption = SelectOption,
    Normalized = any,
>({
    handler,
    isMulti,
    newValue,
    resolveValue,
}: {
    handler?: SelectDenormalizeValue<Opt, Normalized> | undefined;
    isMulti?: IsMulti;
    newValue: IsMulti extends true ? Opt[] : Opt | null;
    resolveValue: ResolveValue;
}): ResolvedChangedValue<ResolveValue, IsMulti, Opt> {
    handler ??= (v => v) as SelectDenormalizeValue<Opt, Normalized>;

    if (resolveValue) {
        const v = isMulti
            ? (newValue as Opt[]).map(v => v.value)
            : (newValue as Opt | null)?.value || null;

        if (isMulti) {
            // @ts-expect-error Unknown
            return (v as Opt['value'][]).map(handler) as Opt['value'][] &
                ResolvedChangedValue<true, true, Opt>;
        }

        if (v) {
            // @ts-expect-error Unknown
            return handler(v as Opt['value']) as Opt['value'] &
                ResolvedChangedValue<true, false, Opt>;
        }

        // @ts-expect-error Unknown
        return null as ResolvedChangedValue<true, false, Opt>;
    }

    if (isMulti) {
        // @ts-expect-error Unknown
        return (newValue as Opt[]).map(opt => ({
            ...opt,
            value: handler(opt.value),
        })) as ResolvedChangedValue<false, true, Opt>;
    }

    if (newValue) {
        // @ts-expect-error Unknown
        return {
            ...newValue,
            value: handler((newValue as Opt).value),
        } as ResolvedChangedValue<false, false, Opt>;
    }

    // @ts-expect-error Unknown
    return null as ResolvedChangedValue<false, false, Opt>;
}

export function RSelectStyle() {
    return (
        <GlobalStyles
            styles={{
                [`.${rSelectClassName}`]: {
                    verticalAlign: 'middle',
                    paddingRight: 10,
                },
            }}
        />
    );
}
