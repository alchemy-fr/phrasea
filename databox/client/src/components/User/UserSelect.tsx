import React from "react";
import {User} from "../../types";
import {getUsers} from "../../api/user";
import RSelectWidget, {RSelectProps, SelectOption} from "../Form/RSelect";
import {FieldValues} from "react-hook-form/dist/types/fields";

export default function UserSelect<TFieldValues extends FieldValues>(props: RSelectProps<TFieldValues, false>) {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const data = await getUsers();

        return data.map((t: User) => ({
            value: t.id,
            label: t.username,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <RSelectWidget
        loadOptions={load}
        {...props}
    />
}
