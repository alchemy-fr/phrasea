import {Controller} from "react-hook-form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";
import AsyncSelect from 'react-select/async';
import React, {ReactNode, useEffect, useState} from "react";
import {AsyncProps} from "react-select/dist/declarations/src/useAsync";
import {useTheme} from "@mui/material";

interface GroupBase<Option> {
    readonly options: readonly Option[];
    readonly label?: string;
}

type Option = {
    label: ReactNode;
    value: string;
    image?: React.ElementType | React.FC | string;
};
export type {Option as SelectOption};

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = ({
    control: Control<TFieldValues>,
    name: FieldPath<TFieldValues>;
} | {
    control?: undefined;
    name?: string;
}) & {
    disabledValues?: string[];
    clearOnSelect?: boolean;
} & AsyncProps<Option, IsMulti, GroupBase<Option>>;

export type {Props as RSelectProps};

const optionsCache: Record<string, Option> = {};

type CompositeValue<IsMulti extends boolean> = IsMulti extends true ? string[] : string | undefined;
type CompositeOption<IsMulti extends boolean> = IsMulti extends true ? Option[] : Option | null;

function valueToOption<IsMulti extends boolean>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti>
): CompositeOption<IsMulti> {
    if (isMulti) {
        if (!value) {
            return [] as any;
        }
        return (value as string[]).map(v => valueToOption(false, v)) as CompositeOption<IsMulti>;
    } else if (value) {
        return (optionsCache[value as string] ?? null) as CompositeOption<IsMulti>;
    }

    return null as CompositeOption<IsMulti>;
}

export default function RSelectWidget<TFieldValues extends FieldValues,
    IsMulti extends boolean = false>({
                                         control,
                                         name,
                                         value: initialValue,
                                         clearOnSelect,
                                         onChange: onChangeProp,
                                         loadOptions,
                                         disabledValues,
                                         isMulti,
                                         ...rest
                                     }: Props<TFieldValues, IsMulti>) {
    const [value, setValue] = useState(initialValue);
    const theme = useTheme();

    useEffect(() => {
        setValue(initialValue);
    }, [initialValue]);

    const loadOptionsWrapper: typeof loadOptions = loadOptions ? async (inputValue: string) => {
        const options = await loadOptions!(inputValue, () => {
        }) as Option[];

        options.forEach(o => {
            optionsCache[o.value] = o;
        })

        return options;
    } : undefined;

    if (control) {
        return <Controller
            control={control}
            name={name}
            render={({field: {onChange, value, ref}}) => {
                return <AsyncSelect<Option, any>
                    {...rest}
                    ref={ref}
                    value={valueToOption(isMulti || false, value as CompositeValue<IsMulti>)}
                    onChange={(newValue, meta) => {
                        const v = isMulti ? (newValue as Option[]).map(v => v.value) : (newValue as Option | null)?.value;
                        onChange(v);
                        onChangeProp && onChangeProp(newValue as any, meta);
                    }}
                    isOptionDisabled={disabledValues ? o => {
                        return disabledValues!.includes(o.value);
                    } : undefined}
                    cacheOptions
                    defaultOptions
                    loadOptions={loadOptionsWrapper}
                    isMulti={isMulti}
                    menuPortalTarget={document.body}
                    styles={{
                        menuPortal: base => ({...base, zIndex: theme.zIndex.tooltip})
                    }}
                />
            }}
        />
    }

    return <AsyncSelect<Option, IsMulti>
        {...rest}
        onChange={(newValue, meta) => {
            onChangeProp && onChangeProp(newValue, meta);
            setValue(!clearOnSelect ? newValue : null);
        }}
        value={value}
        isOptionDisabled={disabledValues ? o => {
            return disabledValues!.includes(o.value);
        } : undefined}
        cacheOptions
        defaultOptions
        loadOptions={loadOptionsWrapper}
        isMulti={isMulti}
    />
}
