import React from "react";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {AssetDataTemplate, getAssetDataTemplates} from "../../api/templates";

type Props<TFieldValues> = {
    workspaceId: string;
} & RSelectProps<TFieldValues, false>;

export default function AssetDataTemplateSelect<TFieldValues extends FieldValues>({
    workspaceId,
    ...rest
}: Props<TFieldValues>) {
    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (await getAssetDataTemplates({
            workspace: workspaceId,
        })).result;

        return data.map((t: AssetDataTemplate) => ({
            value: t.id,
            label: t.name,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <RSelectWidget<TFieldValues, false>
        cacheId={'asset-data-templates'}
        {...rest}
        loadOptions={load}
        isMulti={false as any}
        key={workspaceId}
    />
}
