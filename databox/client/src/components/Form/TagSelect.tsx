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
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {getTagColorStyle} from '../Media/Asset/Facets/TagColor.tsx';
import {useTheme} from '@mui/material';

type TagOption = Readonly<{
    value: string;
    label: string;
    item: Tag;
}>;

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

    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (
            await getTags({
                workspace: workspaceId,
                query: inputValue,
            })
        ).result;

        return data
            .map((t: Tag) => {
                store(t['@id'], t);

                return {
                    value: useIRI ? `${tagNS}/${t.id}` : t.id,
                    label: t.nameTranslated,
                    item: t,
                } as TagOptions;
            })
            .filter(i =>
                i.label.toLowerCase().includes((inputValue || '').toLowerCase())
            );
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
                      marginRight: 8,
                  },
              }
            : {};
    };

    return (
        <AsyncRSelectWidget
            cacheId={'tags'}
            {...rest}
            loadOptions={load}
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
