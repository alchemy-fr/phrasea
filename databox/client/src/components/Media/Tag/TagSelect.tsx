import React from "react";
import {Tag} from "../../../types";
import {getTags} from "../../../api/tag";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "../../Form/RSelect";

type Props<TFieldValues> = {
    workspaceId: string;
} & RSelectProps<TFieldValues, false>;

export default function TagSelect<TFieldValues extends FieldValues>({
                                                                        workspaceId,
                                                                        ...rest
                                                                    }: Props<TFieldValues>) {
    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (await getTags({
            //query: inputValue,
            workspace: workspaceId,
        })).result;

        return data.map((t: Tag) => ({
            value: t.id,
            label: t.name,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <RSelectWidget<TFieldValues, false>
        {...rest}
        loadOptions={load}
        isMulti={true as any}
    />
}
