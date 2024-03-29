import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import AsyncSelect from 'react-select/async';
import React, {useEffect, useState} from 'react';
import {useTheme} from '@mui/material';
import {components, OptionProps} from 'react-select';
import {AsyncProps} from 'react-select/async';

interface GroupBase<Option> {
    readonly options: readonly Option[];
    readonly label?: string;
}

type Option = {
    label: string;
    value: string;
    image?: React.ElementType | React.FC;
};
export type {Option as SelectOption};

export type {Props as RSelectProps};

type CompositeValue<IsMulti extends boolean> = IsMulti extends true
    ? string[]
    : string | undefined;

type CompositeOption<IsMulti extends boolean> = IsMulti extends true
    ? Option[]
    : Option | null;

function valueToOption<IsMulti extends boolean>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti>,
    lastOptions: Record<string, Option>
): CompositeOption<IsMulti> {
    if (isMulti) {
        if (!value) {
            return [] as any;
        }
        return (value as string[]).map(v =>
            valueToOption(false, v, lastOptions)
        ) as CompositeOption<IsMulti>;
    } else if (value) {
        return (lastOptions[value as string] ??
            null) as CompositeOption<IsMulti>;
    }

    return null as CompositeOption<IsMulti>;
}

const ImageOption = (props: OptionProps<Option>) => {
    return (
        <components.Option {...props}>
            {props.data.image && (
                <span
                    style={{
                        verticalAlign: 'middle',
                        paddingRight: 10,
                    }}
                >
                    {React.createElement(props.data.image)}
                </span>
            )}
            {props.data.label}
        </components.Option>
    );
};

const componentsProp = {
    Option: ImageOption,
};

const cache: Record<string, Record<string, Option>> = {};

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
    cacheId?: string;
    disabledValues?: string[];
    clearOnSelect?: boolean;
    disabled?: boolean | undefined;
    cacheOptions?: any;
} & AsyncProps<Option, IsMulti, GroupBase<Option>>;

export default function RSelectWidget<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false
>({
    cacheId,
    control,
    name,
    value: initialValue,
    clearOnSelect,
    onChange: onChangeProp,
    loadOptions,
    disabledValues,
    cacheOptions = true,
    isMulti,
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const [value, setValue] = useState(initialValue);
    const [lastOptions, setLastOptions] = useState<Record<string, Option>>(
        cacheId ? cache[cacheId] ?? {} : {}
    );
    const theme = useTheme();

    useEffect(() => {
        setValue(initialValue);
    }, [initialValue]);

    const loadOptionsWrapper: typeof loadOptions = loadOptions
        ? async (inputValue: string) => {
              const options = (await loadOptions!(
                  inputValue,
                  () => {}
              )) as Option[];

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

              return options;
          }
        : undefined;

    const commonProps: AsyncProps<Option, any, GroupBase<Option>> = {
        styles: {
            menuPortal: base => ({
                ...base,
                zIndex: theme.zIndex.tooltip + 1,
            }),
        },
        menuPortalTarget: document.body,
        isMulti,
        loadOptions: loadOptionsWrapper,
        defaultOptions: true,
        cacheOptions,
        components: rest.components ?? componentsProp,
        isOptionDisabled: disabledValues
            ? (o: Option) => {
                  return disabledValues!.includes(o.value);
              }
            : undefined,
    };

    if (control) {
        return (
            <Controller
                control={control}
                name={name}
                render={({field: {onChange, value, ref}}) => {
                    return (
                        <AsyncSelect<Option, any>
                            {...rest}
                            ref={ref}
                            value={valueToOption(
                                isMulti || false,
                                value as CompositeValue<IsMulti>,
                                lastOptions
                            )}
                            onChange={(newValue, meta) => {
                                const v = isMulti
                                    ? (newValue as Option[]).map(v => v.value)
                                    : (newValue as Option | null)?.value;
                                onChange(v);
                                onChangeProp &&
                                    onChangeProp(newValue as any, meta);
                            }}
                            {...commonProps}
                        />
                    );
                }}
            />
        );
    }

    return (
        <AsyncSelect<Option, IsMulti>
            {...rest}
            onChange={(newValue, meta) => {
                onChangeProp && onChangeProp(newValue, meta);
                setValue(!clearOnSelect ? newValue : null);
            }}
            value={value}
            {...commonProps}
        />
    );
}
