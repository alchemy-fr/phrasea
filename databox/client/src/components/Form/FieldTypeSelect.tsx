import React, {useCallback} from "react";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {fieldTypesIcons} from "../../lib/icons";
import {getAttributeFieldTypes} from "../../api/attributes";

type Props<TFieldValues extends FieldValues> = {} & RSelectProps<TFieldValues, false>;

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
        cacheId={'fieldType'}
        {...rest}
        loadOptions={load}
    />
}
