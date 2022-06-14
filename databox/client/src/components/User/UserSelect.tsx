import React from "react";
import {User} from "../../types";
import {getUsers} from "../../api/user";
import RSelectWidget, {RSelectProps, SelectOption} from "../Form/RSelect";
import {FieldValues} from "react-hook-form/dist/types/fields";

type Props<TFieldValues extends FieldValues> = {
    data?: User[] | undefined;
} & RSelectProps<TFieldValues, false>;

export default function UserSelect<TFieldValues extends FieldValues>({
                                                                         data,
                                                                         ...props
                                                                     }: Props<TFieldValues>) {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const result = !inputValue && data ? data : await getUsers();

        return result.map((t: User) => ({
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
