import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import AsyncSelect from 'react-select/async';
import {ReactNode, useEffect, useState} from 'react';
import {InputLabel, useTheme} from '@mui/material';
import {AsyncProps} from 'react-select/async';
import {OnChangeValue} from 'react-select';
import {createSelectStyles} from './SelectStyles';
import AsyncCreatableSelect from 'react-select/async-creatable';
import {
    GroupBase,
    ImageOption,
    RSelectStyle,
    SelectOption,
} from './RSelectWidget';

type CompositeValue<IsMulti extends boolean> = IsMulti extends true
    ? string[]
    : string | undefined;

type CompositeOption<IsMulti extends boolean> = IsMulti extends true
    ? SelectOption[]
    : SelectOption | null;

export function valueToOption<IsMulti extends boolean>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti>,
    lastOptions: Record<string, SelectOption> = {},
): CompositeOption<IsMulti> {
    if (isMulti) {
        if (!value) {
            return [] as any;
        }
        return (value as string[]).map(v =>
            valueToOption(false, v, lastOptions),
        ) as CompositeOption<IsMulti>;
    } else if (value) {
        return (lastOptions[value as string] ??
            null) as CompositeOption<IsMulti>;
    }

    return null as CompositeOption<IsMulti>;
}

const cache: Record<string, Record<string, SelectOption>> = {};

export type RSelectOnCreate = (
    inputValue: string,
    onCreate: (option: SelectOption) => void,
) => void;

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = (
    | {
          control: Control<TFieldValues>;
          name: FieldPath<TFieldValues>;
      }
    | {
          control?: undefined;
          name?: string;
      }
) & {
    error?: boolean | undefined;
    cacheId?: string;
    disabledValues?: string[];
    clearOnSelect?: boolean;
    disabled?: boolean | undefined;
    cacheOptions?: any;
    onCreate?: RSelectOnCreate;
    label?: ReactNode;
    inputHeight?: number;
    menuWidth?: number;
} & AsyncProps<SelectOption, IsMulti, GroupBase<SelectOption>>;

export type {Props as AsyncRSelectProps};
export default function AsyncRSelectWidget<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
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
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const [value, setValue] = useState(initialValue);
    const [lastOptions, setLastOptions] = useState<
        Record<string, SelectOption>
    >(cacheId ? cache[cacheId] ?? {} : {});
    const theme = useTheme();

    const componentsProp = {
        Option: ImageOption,
        ...(rest.components ?? {}),
    };

    const updateLastOptions = (options: SelectOption[]) => {
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
            updateLastOptions([initialValue as SelectOption]);
            setValue((initialValue as SelectOption).value as any);
        } else {
            setValue(initialValue);
        }
    }, [initialValue]);

    const loadOptionsWrapper: typeof loadOptions =
        loadOptions && !isDisabled
            ? async (inputValue: string) => {
                  const options = (await loadOptions!(
                      inputValue,
                      () => {},
                  )) as SelectOption[];

                  updateLastOptions(options);

                  return options;
              }
            : undefined;

    const SelectComponent = onCreate ? AsyncCreatableSelect : AsyncSelect;

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
                                  ) as OnChangeValue<SelectOption, IsMulti>;
                                  const v = isMulti
                                      ? (newValue as SelectOption[]).map(
                                            v => v.value,
                                        )
                                      : (newValue as SelectOption | null)
                                            ?.value;

                                  updateLastOptions([option]);

                                  onChange(v);

                                  onChangeProp &&
                                      onChangeProp(newValue, {
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
                            <SelectComponent<SelectOption, any>
                                {...rest}
                                ref={ref}
                                required={required}
                                components={componentsProp}
                                value={valueToOption(
                                    isMulti || false,
                                    value as CompositeValue<IsMulti>,
                                    lastOptions,
                                )}
                                onChange={(newValue, meta) => {
                                    const v = isMulti
                                        ? (newValue as SelectOption[]).map(
                                              v => v.value,
                                          )
                                        : (newValue as SelectOption | null)
                                              ?.value ?? null;
                                    onChange(v);
                                    onChangeProp &&
                                        onChangeProp(newValue as any, meta);
                                }}
                                isOptionDisabled={
                                    disabledValues
                                        ? o => {
                                              return disabledValues!.includes(
                                                  o.value,
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
                                styles={createSelectStyles(
                                    theme,
                                    error,
                                    styles,
                                    inputHeight,
                                    menuWidth,
                                )}
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
                  ) as OnChangeValue<SelectOption, IsMulti>;
                  setValue(newValue);
                  onChangeProp &&
                      onChangeProp(newValue, {
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
            <SelectComponent<SelectOption, IsMulti>
                isClearable={!required}
                {...rest}
                required={required}
                components={componentsProp}
                onChange={(newValue, meta) => {
                    onChangeProp && onChangeProp(newValue, meta);
                    setValue(!clearOnSelect ? newValue : null);
                }}
                value={valueToOption(
                    isMulti || false,
                    value as CompositeValue<IsMulti>,
                    lastOptions,
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
                styles={createSelectStyles(
                    theme,
                    error,
                    styles,
                    inputHeight,
                    menuWidth,
                )}
            />
        </>
    );
}
