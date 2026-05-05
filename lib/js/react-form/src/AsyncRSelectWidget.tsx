import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import AsyncSelect from 'react-select/async';
import {ReactNode, useEffect, useMemo, useState} from 'react';
import {InputLabel, useTheme} from '@mui/material';
import {AsyncProps} from 'react-select/async';
import {OnChangeValue} from 'react-select';
import {createSelectStyles} from './selectStyles';
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

type CompositeOption<
    IsMulti extends boolean,
    Opt extends SelectOption,
> = IsMulti extends true ? Opt[] : Opt | null;

export function valueToOption<IsMulti extends boolean, Opt extends SelectOption>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti>,
    lastOptions: Record<string, Opt> = {}
): CompositeOption<IsMulti, Opt> {
    if (isMulti) {
        if (!value) {
            return [] as any;
        }
        return (value as string[]).map(v =>
            valueToOption(false, v, lastOptions)
        ) as CompositeOption<IsMulti, Opt>;
    } else if (value) {
        return (lastOptions[value as string] ?? null) as CompositeOption<
            IsMulti,
            Opt
        >;
    }

    return null as CompositeOption<IsMulti, Opt>;
}

const cache: Record<string, Record<string, any>> = {};

export type RSelectOnCreate<Opt extends SelectOption> = (
    inputValue: string,
    onCreate: (option: Opt) => void
) => void;

type Props<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
    Opt extends SelectOption = SelectOption,
> = (
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
    onCreate?: RSelectOnCreate<Opt>;
    label?: ReactNode;
    inputHeight?: number;
    menuWidth?: number;
} & AsyncProps<Opt, IsMulti, GroupBase<Opt>>;

export type {Props as AsyncRSelectProps};

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
    ...rest
}: Props<TFieldValues, IsMulti, Opt>) {
    const [value, setValue] = useState(initialValue);
    const [lastOptions, setLastOptions] = useState<Record<string, Opt>>(
        cacheId ? (cache[cacheId] ?? {}) : {}
    );
    const theme = useTheme();

    const componentsProp = {
        Option: ImageOption,
        ...(rest.components ?? {}),
    };

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
        loadOptions && !isDisabled
            ? async (inputValue: string) => {
                  const options = (await loadOptions!(
                      inputValue,
                      () => {}
                  )) as Opt[];

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
                                  ) as OnChangeValue<Opt, IsMulti>;
                                  const v = isMulti
                                      ? (newValue as Opt[]).map(v => v.value)
                                      : (newValue as Opt | null)?.value;

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
                            <SelectComponent<Opt, any>
                                {...rest}
                                ref={ref}
                                required={required}
                                components={componentsProp}
                                value={valueToOption(
                                    isMulti || false,
                                    value as CompositeValue<IsMulti>,
                                    lastOptions
                                )}
                                onChange={(newValue, meta) => {
                                    const v = isMulti
                                        ? (newValue as Opt[]).map(
                                              v => v.value
                                          )
                                        : ((newValue as Opt | null)?.value ??
                                          null);
                                    onChange(v);
                                    onChangeProp &&
                                        onChangeProp(newValue as any, meta);
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
            <SelectComponent<Opt, IsMulti>
                isClearable={!required}
                {...rest}
                required={required}
                components={componentsProp}
                onChange={(newValue, meta) => {
                    const v = isMulti
                        ? (newValue as Opt[]).map(v => v.value)
                        : ((newValue as Opt | null)?.value ?? null);

                    onChangeProp && onChangeProp(newValue, meta);
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
