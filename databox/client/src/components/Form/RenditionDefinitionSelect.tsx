import React, {useCallback} from "react";
import {FieldValues} from "react-hook-form/dist/types/fields";
import RSelectWidget, {RSelectProps, SelectOption} from "./RSelect";
import {
    getRenditionClasses,
    getRenditionDefinitions,
    renditionClassNS,
    renditionDefinitionNS
} from "../../api/rendition";
import {RenditionClass, RenditionDefinition} from "../../types";

type Props<TFieldValues> = {
    workspaceId: string;
} & RSelectProps<TFieldValues, false>;

export default function RenditionDefinitionSelect<TFieldValues extends FieldValues>({
                                                                                   workspaceId,
                                                                                   ...rest
                                                                               }: Props<TFieldValues>) {
    const load = useCallback(async (inputValue: string): Promise<SelectOption[]> => {
        const data = (await getRenditionDefinitions({
            workspaceIds: [workspaceId],
        }));

        return data.result.map((t: RenditionDefinition) => ({
            value: `${renditionDefinitionNS}/${t.id}`,
            label: t.name,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    }, []);

    return <RSelectWidget<TFieldValues>
        cacheId={'rend-definitions'}
        {...rest}
        loadOptions={load}
    />
}
