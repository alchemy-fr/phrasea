import {Controller} from "react-hook-form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";
import AsyncSelect from 'react-select/async';
import React, {ReactNode} from "react";
import {AsyncProps} from "react-select/dist/declarations/src/useAsync";

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

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    control: Control<TFieldValues>,
    name: FieldPath<TFieldValues>;
    disabledValues?: string[];
} & AsyncProps<Option, IsMulti, GroupBase<Option>>;

export type {Props as RSelectProps};

const optionsCache: Record<string, Option> = {};

function valueToOption(value: string | string[] | undefined): Option | Option[] | undefined {
    if (value instanceof Array) {
        return value.map(valueToOption) as Option[];
    } else if (value) {
        return optionsCache[value] || undefined;
    }
}

export default function RSelectWidget<TFieldValues extends FieldValues, IsMulti extends boolean>({
                                                                                                             control,
                                                                                                             name,
                                                                                                             loadOptions,
                                                                                                             disabledValues,
                                                                                                             isMulti,
                                                                                                             options,
    ...rest
                                                                                                         }: Props<TFieldValues, IsMulti>) {
        const loadOptionsWrapper: typeof loadOptions = loadOptions ? async (inputValue: string) => {
            const options = await loadOptions!(inputValue, () => {}) as Option[];

            options.forEach(o => {
                optionsCache[o.value] = o;
            })

            return options;
        } : undefined;

    return <Controller
        control={control}
        name={name}
        render={({field: {onChange, value, ref}}) => {
            return <AsyncSelect
                ref={ref}
                value={valueToOption(value as string | string[] | undefined) as any}
                onChange={(val) => {
                    onChange(isMulti ? (val as Option[]).map(v => v.value) : (val as Option | undefined)?.value);
                }}
                isOptionDisabled={disabledValues ? o => {
                    return disabledValues!.includes(o.value);
                } : undefined}
                options={options}
                cacheOptions
                defaultOptions
                isMulti={isMulti}
                loadOptions={loadOptionsWrapper}
                {...rest}
            />
        }}
    />
}
