import {Controller} from "react-hook-form";
import {FieldValues} from "react-hook-form/dist/types/fields";
import {Control} from "react-hook-form/dist/types/form";
import {FieldPath} from "react-hook-form/dist/types";
import AsyncSelect from 'react-select/async';
import React, {ReactNode} from "react";
import {AsyncProps} from "react-select/dist/declarations/src/useAsync";
import {ActionMeta, OnChangeValue} from "react-select/dist/declarations/src/types";

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

type CompositeValue<IsMulti extends boolean> = IsMulti extends true ? string[] : string | undefined;
type CompositeOption<IsMulti extends boolean> = IsMulti extends true ? Option[] : Option | undefined;

function valueToOption<IsMulti extends boolean>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti>
): CompositeOption<IsMulti> {
    if (isMulti) {
        return (value as string[]).map(v => valueToOption(false, v)) as CompositeOption<IsMulti>;
    } else if (value) {
        return optionsCache[value as string] as CompositeOption<IsMulti>;
    }

    return undefined as CompositeOption<IsMulti>;
}

export default function RSelectWidget<TFieldValues extends FieldValues, IsMulti extends boolean = false>({
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
            return <AsyncSelect<Option, any>
                ref={ref}
                value={valueToOption(isMulti || false, value as CompositeValue<IsMulti>)}
                onChange={((newValue: any) => {
                    console.log('newValue', newValue);
                    onChange(isMulti ? (newValue as Option[]).map(v => v.value) : (newValue as Option | undefined)?.value);
                }) as any}
                isOptionDisabled={disabledValues ? o => {
                    return disabledValues!.includes(o.value);
                } : undefined}
                options={options}
                cacheOptions
                defaultOptions
                loadOptions={loadOptionsWrapper}
                {...rest}
                isMulti={isMulti}
            />
        }}
    />
}
