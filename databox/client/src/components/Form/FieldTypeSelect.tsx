import React, {useCallback} from "react";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {getAttributeFieldTypes} from "../../api/asset";
import {fieldTypesIcons} from "../../lib/icons";

type Props<TFieldValues> = {} & RSelectProps<TFieldValues, false>;

export default function FieldTypeSelect<TFieldValues extends FieldValues>({
                                                                              ...rest
                                                                          }: Props<TFieldValues>) {

    const load = useCallback(async (inputValue: string): Promise<SelectOption[]> => {
        const data = await getAttributeFieldTypes();

        return data.filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        ).map(d => ({
            ...d,
            image: fieldTypesIcons[d.value] ?? fieldTypesIcons.text,
        }));
    }, []);

    return <RSelectWidget<TFieldValues>
        {...rest}
        loadOptions={load}
    />
}
