import React from "react";
import {SelectOption} from "../Form/SelectWidget";
import {Group} from "../../types";
import {getGroups} from "../../api/user";
import RSelectWidget, {RSelectProps} from "../Form/RSelect";
import {FieldValues} from "react-hook-form/dist/types/fields";

export default function GroupSelect<TFieldValues extends FieldValues>(props: RSelectProps<TFieldValues, false>) {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const data = await getGroups();

        return data.map((t: Group) => ({
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
