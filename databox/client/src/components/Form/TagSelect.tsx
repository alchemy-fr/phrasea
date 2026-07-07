import {Tag} from '../../types';
import {getTags} from '../../api/tag';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
} from '@alchemy/react-form';
import {WorkspaceContext} from '../../context/WorkspaceContext.tsx';
import React from 'react';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {getTagColorStyle} from '../Media/Asset/Facets/TagColor.tsx';
import {useTheme} from '@mui/material';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';

type TagOption = Readonly<{
    item: Tag;
}> &
    SelectOption;

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    useIRI?: boolean;
    multiple: IsMulti;
} & AsyncRSelectProps<TFieldValues, IsMulti, TagOption>;

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

    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getTags({
                ...props,
                workspace: workspaceId,
            }),
        map: t => {
            store(t['@id'], t);

            return {
                value: useIRI ? createIriFromId(EntityName.Tag, t.id) : t.id,
                label: t.displayName,
                item: t,
            } as TagOption;
        },
    });

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
        <AsyncRSelectWidget<TFieldValues, IsMulti, TagOption>
            cacheId={'tags'}
            {...rest}
            loadOptions={loadOptions}
            isMulti={multiple}
            key={workspaceId}
            styles={
                {
                    singleValue: tagStyle as any,
                    multiValueLabel: tagStyle as any,
                    option: tagStyle as any,
                    ...(styles ?? {}),
                } as any
            }
        />
    );
}

export type TagOptions = {
    item: Tag;
} & SelectOption;
