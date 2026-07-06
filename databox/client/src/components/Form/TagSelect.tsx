import {Tag} from '../../types';
import {getTags, tagNS} from '../../api/tag';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
    LoadPaginated,
} from '@alchemy/react-form';
import {WorkspaceContext} from '../../context/WorkspaceContext.tsx';
import React from 'react';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {getTagColorStyle} from '../Media/Asset/Facets/TagColor.tsx';
import {useTheme} from '@mui/material';

type TagOption = Readonly<{
    item: Tag;
}> &
    SelectOption;

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    useIRI?: boolean;
    multiple: IsMulti;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function TagSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({
    workspaceId: wsId,
    useIRI = true,
    multiple,
    styles,
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const workspaceContext = React.useContext(WorkspaceContext);
    const store = useEntitiesStore(s => s.store);
    const theme = useTheme();

    const workspaceId = wsId ?? workspaceContext?.workspaceId;

    const load: LoadPaginated<TagOption> = async (
        inputValue: string,
        nextUrl?: string
    ) => {
        const data = await getTags({
            nextUrl,
            workspace: workspaceId,
            query: inputValue,
        });

        return {
            result: data.result
                .map((t: Tag) => {
                    store(t['@id'], t);

                    return {
                        value: useIRI ? `${tagNS}/${t.id}` : t.id,
                        label: t.displayName,
                        item: t,
                    } as TagOptions;
                })
                .filter(i =>
                    i.label
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                ),
            next: data.next,
        };
    };

    const tagStyle = (_base: any, state: any) => {
        return (state.data as TagOption).item?.color
            ? {
                  'alignItems': 'center',
                  'display': 'flex',
                  ':before': {
                      ...getTagColorStyle(
                          theme,
                          (state.data as TagOption).item.color
                      ),
                      content: '" "',
                      display: 'block',
                  },
              }
            : {};
    };

    return (
        <AsyncRSelectWidget
            cacheId={'tags'}
            {...rest}
            loadPaginated={load}
            isMulti={multiple}
            key={workspaceId}
            styles={{
                singleValue: tagStyle,
                multiValueLabel: tagStyle,
                option: tagStyle,
                ...(styles ?? {}),
            }}
        />
    );
}

export type TagOptions = {
    item: Tag;
} & SelectOption;
