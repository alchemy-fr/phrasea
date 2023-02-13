import React from "react";
import {User} from "../../types";
import {getUsers} from "../../api/user";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {FieldValues} from "react-hook-form/dist/types/fields";

type Props<TFieldValues extends FieldValues> = {
    data?: Promise<User[]> | undefined;
} & RSelectProps<TFieldValues, false>;

export default function UserSelect<TFieldValues extends FieldValues>({
    data,
    ...props
}: Props<TFieldValues>) {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const result = await (!inputValue && data ? data : getUsers());

        return result.map((t: User) => ({
            value: t.id,
            label: t.username,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <RSelectWidget
        cacheId={'users'}
        loadOptions={load}
        {...props}
    />
}
