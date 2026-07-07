import {Controller, FieldValues} from 'react-hook-form';
import {useEffect, useMemo, useState} from 'react';
import {InputLabel, useTheme} from '@mui/material';
import Select, {OnChangeValue} from 'react-select';
import {createSelectStyles} from './selectStyles';
import {ImageOption, RSelectStyle} from './RSelectWidget';
import {
    AsyncPaginateCreatableType,
    AsyncPaginateType,
    AsyncRSelectProps,
    CompositeValue,
    SelectOption,
} from '../types';
import {valueToOption} from './funcs';
import Creatable from 'react-select/creatable';
import {withAsyncPaginate} from 'react-select-async-paginate';

const CreatableAsyncPaginate = withAsyncPaginate(
    Creatable
) as AsyncPaginateCreatableType;

const AsyncPaginate = withAsyncPaginate(Select) as AsyncPaginateType;

const cache: Record<string, Record<string, any>> = {};

export default function AsyncRSelectWidget<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
    Opt extends SelectOption = SelectOption,
>({
    cacheId,
    control,
    name,
    onCreate,
    value: initialValue,
    clearOnSelect,
    onChange: onChangeProp,
    loadOptions,
    disabledValues,
    error,
    cacheOptions = true,
    isMulti,
    required,
    label,
    styles,
    inputHeight,
    menuWidth = 300,
    isDisabled,
    components,
    ...rest
}: AsyncRSelectProps<TFieldValues, IsMulti, Opt>) {
    const [value, setValue] = useState(initialValue);
    const [lastOptions, setLastOptions] = useState<Record<string, Opt>>(
        cacheId ? (cache[cacheId] ?? {}) : {}
    );
    const theme = useTheme();

    const componentsProp = useMemo(
        () => ({
            Option: ImageOption,
            ...(components ?? {}),
        }),
        [components]
    );

    const computedStyles = useMemo(() => {
        return createSelectStyles(theme, error, styles, inputHeight, menuWidth);
    }, [theme, error, inputHeight, menuWidth]);

    const updateLastOptions = (options: Opt[]) => {
        setLastOptions(p => {
            const last = {...p};
            options.forEach(o => {
                last[o.value] = o;
                if (cacheId) {
                    if (!cache[cacheId]) {
                        cache[cacheId] = {};
                    }
                    cache[cacheId][o.value] = o;
                }
            });

            return last;
        });
    };

    useEffect(() => {
        if (
            initialValue &&
            typeof initialValue === 'object' &&
            Object.prototype.hasOwnProperty.call(initialValue, 'value')
        ) {
            updateLastOptions([initialValue as Opt]);
            setValue((initialValue as Opt).value as any);
        } else {
            setValue(initialValue);
        }
    }, [initialValue]);

    const loadOptionsWrapper: typeof loadOptions =
        !isDisabled && loadOptions
            ? async (
                  inputValue: string,
                  loadedOptions: any,
                  additional: any
              ) => {
                  const result = await loadOptions(
                      inputValue,
                      loadedOptions,
                      additional
                  );

                  updateLastOptions(result.options as Opt[]);

                  return result;
              }
            : () => {
                  return {
                      options: [],
                      hasMore: false,
                  };
              };

    const SelectComponent = onCreate ? CreatableAsyncPaginate : AsyncPaginate;

    if (control) {
        return (
            <Controller
                control={control}
                name={name}
                rules={{
                    required,
                }}
                render={({field: {onChange, value, ref}}) => {
                    const onCreateOption = onCreate
                        ? (inputValue: string) => {
                              onCreate(inputValue, option => {
                                  const newValue = (
                                      isMulti ? [option] : option
                                  ) as OnChangeValue<Opt, IsMulti>;
                                  const v = isMulti
                                      ? (newValue as Opt[]).map(v => v.value)
                                      : (newValue as Opt | null)?.value;

                                  updateLastOptions([option]);
                                  onChange(v);
                                  onChangeProp?.(newValue, {
                                      action: 'select-option',
                                      option,
                                  });
                              });
                          }
                        : undefined;

                    return (
                        <>
                            {label ? <InputLabel>{label}</InputLabel> : ''}
                            <RSelectStyle />
                            <SelectComponent<Opt, any>
                                {...rest}
                                selectRef={ref}
                                required={required}
                                components={componentsProp}
                                value={valueToOption(
                                    isMulti || false,
                                    value as CompositeValue<IsMulti>,
                                    lastOptions
                                )}
                                onChange={(newValue, meta) => {
                                    const v = isMulti
                                        ? (newValue as Opt[]).map(v => v.value)
                                        : ((newValue as Opt | null)?.value ??
                                          null);
                                    onChange(v);
                                    onChangeProp?.(newValue as any, meta);
                                }}
                                isOptionDisabled={
                                    disabledValues
                                        ? o => {
                                              return disabledValues!.includes(
                                                  o.value
                                              );
                                          }
                                        : undefined
                                }
                                cacheOptions={cacheOptions}
                                defaultOptions
                                isClearable={!required}
                                loadOptions={loadOptionsWrapper}
                                isMulti={isMulti}
                                menuPortalTarget={document.body}
                                onCreateOption={onCreateOption}
                                styles={computedStyles}
                            />
                        </>
                    );
                }}
            />
        );
    }

    const onCreateOption = onCreate
        ? (inputValue: string) => {
              onCreate(inputValue, option => {
                  const newValue = (
                      isMulti ? [option] : option
                  ) as OnChangeValue<Opt, IsMulti>;
                  updateLastOptions([option]);
                  setValue(newValue);
                  onChangeProp?.(newValue, {
                      action: 'select-option',
                      option,
                  });
              });
          }
        : undefined;

    return (
        <>
            {label ? <InputLabel>{label}</InputLabel> : ''}
            <RSelectStyle />
            <SelectComponent<Opt, IsMulti>
                isClearable={!required}
                {...rest}
                required={required}
                components={componentsProp}
                onChange={(newValue, meta) => {
                    const v = isMulti
                        ? (newValue as Opt[]).map(v => v.value)
                        : ((newValue as Opt | null)?.value ?? null);

                    onChangeProp?.(newValue, meta);
                    setValue(!clearOnSelect ? (v as any) : null);
                }}
                value={valueToOption(
                    isMulti || false,
                    value as CompositeValue<IsMulti>,
                    lastOptions
                )}
                isOptionDisabled={
                    disabledValues
                        ? o => {
                              return disabledValues!.includes(o.value);
                          }
                        : undefined
                }
                cacheOptions={cacheOptions}
                defaultOptions
                loadOptions={loadOptionsWrapper}
                isMulti={isMulti}
                menuPortalTarget={document.body}
                onCreateOption={onCreateOption}
                styles={computedStyles}
            />
        </>
    );
}
