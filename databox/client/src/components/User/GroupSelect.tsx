import React from "react";
import {SelectOption} from "../Form/SelectWidget";
import {Group} from "../../types";
import {getGroups} from "../../api/user";
import RSelectWidget, {RSelectProps} from "../Form/RSelect";
import {FieldValues} from "react-hook-form/dist/types/fields";

type Props<TFieldValues extends FieldValues> = {
    data?: Promise<Group[]> | undefined;
} & RSelectProps<TFieldValues, false>;

export default function GroupSelect<TFieldValues extends FieldValues>({
                                                                          data,
                                                                          ...props
                                                                      }: Props<TFieldValues>) {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const result = await (!inputValue && data ?  data : getGroups());

        return result.map((t: Group) => ({
            value: t.id,
            label: t.name,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <RSelectWidget
        loadOptions={load}
        {...props}
    />
}
