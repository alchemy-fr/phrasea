import {AttributeEntity} from '../../types';
import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget, SelectOption,} from '@alchemy/react-form';
import {WorkspaceContext} from "../../context/WorkspaceContext.tsx";
import React from "react";
import {getAttributeEntities} from "../../api/attributeEntity.ts";

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    multiple: IsMulti;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function AttributeEntitySelect<TFieldValues extends FieldValues, IsMulti extends boolean>({
    workspaceId: wsId,
    multiple,
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const workspaceContext = React.useContext(WorkspaceContext);

    const workspaceId = wsId ?? workspaceContext?.workspaceId;
    if (!workspaceId) {
        throw new Error('Missing workspace context');
    }

    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (
            await getAttributeEntities({
                workspace: workspaceId,
                query: inputValue,
            })
        ).result;

        return data
            .map((t: AttributeEntity) => ({
                value: t.id,
                label: t.value,
                item: t,
            }));
    };

    return (
        <AsyncRSelectWidget
            cacheId={'attribute-items'}
            {...rest}
            loadOptions={load}
            isMulti={multiple}
            key={workspaceId}
        />
    );
}
