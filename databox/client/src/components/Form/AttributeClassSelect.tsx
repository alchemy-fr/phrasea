import React, {useCallback} from "react";
import {AttributeClass} from "../../types";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {attributeClassNS, getAttributeClasses} from "../../api/attributes";

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & RSelectProps<TFieldValues, false>;

export default function AttributeClassSelect<TFieldValues extends FieldValues>({
                                                                        workspaceId,
                                                                        ...rest
                                                                    }: Props<TFieldValues>) {
    const load = useCallback(async (inputValue: string): Promise<SelectOption[]> => {
        const data = (await getAttributeClasses(workspaceId)).result;

        return data.map((t: AttributeClass) => ({
            value: `${attributeClassNS}/${t.id}`,
            label: t.name,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    }, []);

    return <RSelectWidget<TFieldValues>
        cacheId={'attr-classes'}
        {...rest}
        loadOptions={load}
    />
}
