import {Tag} from '../../types';
import {getTags, tagNS} from '../../api/tag';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
} from '@alchemy/react-form';
import {WorkspaceContext} from '../../context/WorkspaceContext.tsx';
import React from 'react';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    multiple: IsMulti;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function TagSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({workspaceId: wsId, multiple, ...rest}: Props<TFieldValues, IsMulti>) {
    const workspaceContext = React.useContext(WorkspaceContext);

    const workspaceId = wsId ?? workspaceContext?.workspaceId;
    if (!workspaceId) {
        throw new Error('Missing workspace context');
    }

    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (
            await getTags({
                workspace: workspaceId,
                query: inputValue,
            })
        ).result;

        return data
            .map(
                (t: Tag) =>
                    ({
                        value: `${tagNS}/${t.id}`,
                        label: t.nameTranslated,
                        item: t,
                    }) as TagOptions
            )
            .filter(i =>
                i.label.toLowerCase().includes((inputValue || '').toLowerCase())
            );
    };

    return (
        <AsyncRSelectWidget
            cacheId={'tags'}
            {...rest}
            loadOptions={load}
            isMulti={multiple}
            key={workspaceId}
        />
    );
}

export type TagOptions = {
    item: Tag;
} & SelectOption;
