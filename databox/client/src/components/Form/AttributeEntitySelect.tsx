import {AttributeEntity, AttributeEntityStatus, EntityList} from '../../types';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    RSelectOnCreate,
    SelectOption,
} from '@alchemy/react-form';
import {WorkspaceContext} from '../../context/WorkspaceContext.tsx';
import React from 'react';
import {
    formatAttributeEntityLabel,
    getAttributeEntities,
} from '../../api/attributeEntity.ts';
import {useModals} from '@alchemy/navigation';
import CreateAttributeEntityDialog from '../AttributeEntity/CreateAttributeEntityDialog.tsx';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper';
import {useTheme} from '@mui/material';
import {CSSObjectWithLabel, StylesConfig} from 'react-select';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    multiple: IsMulti;
    allowNew?: boolean;
    list: EntityList;
} & AsyncRSelectProps<TFieldValues, IsMulti, AttributeEntityOption>;

export default function AttributeEntitySelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({
    workspaceId: wsId,
    multiple,
    list,
    allowNew = true,
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const {openModal} = useModals();
    const store = useEntitiesStore(s => s.store);
    const workspaceContext = React.useContext(WorkspaceContext);
    const workspaceId = wsId ?? workspaceContext?.workspaceId;
    const theme = useTheme();

    const onCreate: RSelectOnCreate<AttributeEntityOption> | undefined =
        allowNew && list.allowNewValues && workspaceId
            ? (inputValue, onCreate) => {
                  openModal(CreateAttributeEntityDialog, {
                      value: inputValue,
                      list,
                      workspaceId,
                      onCreate: (d: AttributeEntity) => {
                          onCreate({
                              label: d.value,
                              value: d.id,
                              item: d,
                          } as AttributeEntityOption);
                      },
                  });
              }
            : undefined;

    const load = async (
        inputValue: string
    ): Promise<AttributeEntityOption[]> => {
        const data = (
            await getAttributeEntities({
                list: list.id,
                value: inputValue,
            })
        ).result;

        return data.map((t: AttributeEntity) => {
            store(t['@id'], t);

            return {
                value: t.id,
                label: formatAttributeEntityLabel(t),
                item: t,
            };
        });
    };

    const entityStyle = (data: AttributeEntityOption): CSSObjectWithLabel => {
        const status = data.item?.status;

        return status !== undefined && status !== AttributeEntityStatus.Approved
            ? {
                  'alignItems': 'center',
                  'display': 'flex',
                  ':before': {
                      marginRight: theme.spacing(1),
                      borderRadius: '50%',
                      backgroundColor:
                          status === AttributeEntityStatus.Rejected
                              ? theme.palette.error.main
                              : theme.palette.warning.main,
                      height: 15,
                      width: 15,
                      content: '" "',
                      display: 'block',
                  },
              }
            : {};
    };
    return (
        <AsyncRSelectWidget<TFieldValues, IsMulti, AttributeEntityOption>
            cacheId={'attribute-items'}
            {...rest}
            loadOptions={load}
            isMulti={multiple}
            key={workspaceId}
            onCreate={onCreate}
            styles={{
                option: (_base, state) => entityStyle(state.data),
                singleValue: (_base, state) => entityStyle(state.data),
                ...(rest.styles ?? {}),
            }}
        />
    );
}

export type AttributeEntityOption = {
    item: AttributeEntity;
} & SelectOption;
