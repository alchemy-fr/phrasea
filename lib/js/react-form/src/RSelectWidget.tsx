import {Controller} from 'react-hook-form';
import {FieldValues} from 'react-hook-form';
import {Control} from 'react-hook-form';
import {FieldPath} from 'react-hook-form';
import React, {ReactNode, useEffect, useState} from 'react';
import {GlobalStyles, InputLabel, useTheme} from '@mui/material';
import Select, {CommonProps, components, OptionProps} from 'react-select';
import {createSelectStyles} from './SelectStyles';
import {valueToOption} from './AsyncRSelectWidget';
import CreatableSelect, {CreatableProps} from 'react-select/creatable';

export interface GroupBase<Option> {
    readonly options: readonly Option[];
    readonly label?: string;
}

type Option = {
    label: string;
    value: string;
    image?: React.ElementType | React.FC;
    item?: object | undefined;
};
export type {Option as SelectOption};

export const rSelectClassName = 'rselect-img';

export const ImageOption = (props: OptionProps<Option>) => {
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
};

const componentsProp = {
    Option: ImageOption,
};

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
    clearOnSelect?: boolean;
    disabled?: boolean | undefined;
    label?: ReactNode;
    allowCreate?: boolean;
    inputHeight?: number;
    menuWidth?: number;
    creatableProps?: Partial<
        CreatableProps<Option, IsMulti, GroupBase<Option>>
    >;
} & Partial<CommonProps<Option, IsMulti, GroupBase<Option>>['selectProps']>;

export type {Props as RSelectProps};

export default function RSelectWidget<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
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
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const [value, setValue] = useState(initialValue);
    const theme = useTheme();

    useEffect(() => {
        setValue(initialValue);
    }, [initialValue]);

    const indexedOptions = React.useMemo(() => {
        const index: Record<string, Option> = {};
        (options as Option[] | undefined)?.forEach(o => {
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
                render={({field: {onChange, value, ref}}) => {
                    return (
                        <>
                            {label ? <InputLabel>{label}</InputLabel> : ''}
                            <RSelectStyle />
                            <Component<Option, any>
                                {...rest}
                                {...creatableProps}
                                options={options}
                                ref={ref}
                                required={required}
                                components={componentsProp}
                                value={
                                    allowCreate
                                        ? value
                                        : valueToOption(
                                              isMulti || false,
                                              value || undefined,
                                              indexedOptions,
                                          )
                                }
                                onChange={(newValue, meta) => {
                                    const v = isMulti
                                        ? allowCreate
                                            ? newValue
                                            : (newValue as Option[]).map(
                                                  v => v.value,
                                              )
                                        : allowCreate
                                          ? newValue
                                          : (newValue as Option | null)?.value;
                                    onChange(v);
                                    onChangeProp &&
                                        onChangeProp(newValue as any, meta);
                                }}
                                isClearable={!required}
                                isMulti={isMulti}
                                menuPortalTarget={document.body}
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

    return (
        <>
            {label ? <InputLabel>{label}</InputLabel> : ''}
            <RSelectStyle />
            <Component<Option, IsMulti>
                {...rest}
                options={options}
                required={required}
                components={componentsProp}
                onChange={(newValue, meta) => {
                    onChangeProp && onChangeProp(newValue, meta);
                    setValue(!clearOnSelect ? newValue : null);
                }}
                value={valueToOption(
                    isMulti || false,
                    value as any,
                    indexedOptions,
                )}
                isClearable={!required}
                isMulti={isMulti}
                menuPortalTarget={document.body}
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
