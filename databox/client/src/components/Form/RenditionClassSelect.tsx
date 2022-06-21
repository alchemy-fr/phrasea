import React, {useCallback} from "react";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {getRenditionClasses} from "../../api/rendition";
import {RenditionClass} from "../../types";

type Props<TFieldValues> = {
    workspaceId: string;
} & RSelectProps<TFieldValues, false>;

export default function RenditionClassSelect<TFieldValues extends FieldValues>({
                                                                        workspaceId,
                                                                        ...rest
                                                                    }: Props<TFieldValues>) {
    const load = useCallback(async (inputValue: string): Promise<SelectOption[]> => {
        const data = (await getRenditionClasses(workspaceId));

        return data.map((t: RenditionClass) => ({
            value: `/rendition-classes/${t.id}`,
            label: t.name,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    }, []);

    return <RSelectWidget<TFieldValues>
        {...rest}
        loadOptions={load}
    />
}
