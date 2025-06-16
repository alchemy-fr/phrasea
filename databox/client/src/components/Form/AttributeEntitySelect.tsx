import {AttributeEntity} from '../../types';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    RSelectOnCreate,
    SelectOption,
} from '@alchemy/react-form';
import {WorkspaceContext} from '../../context/WorkspaceContext.tsx';
import React from 'react';
import {getAttributeEntities} from '../../api/attributeEntity.ts';
import {useModals} from '@alchemy/navigation';
import CreateAttributeEntityDialog from '../AttributeEntity/CreateAttributeEntityDialog.tsx';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    multiple: IsMulti;
    allowNew?: boolean;
    list: string;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

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

    const onCreate: RSelectOnCreate | undefined =
        allowNew && workspaceId
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
                          });
                      },
                  });
              }
            : undefined;

    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (
            await getAttributeEntities({
                list,
                query: inputValue,
            })
        ).result;

        return data.map((t: AttributeEntity) => {
            store(t['@id'], t);

            return {
                value: t.id,
                label: getBestTranslatedValue(t.translations, t.value),
                item: t,
            };
        });
    };

    return (
        <AsyncRSelectWidget
            cacheId={'attribute-items'}
            {...rest}
            loadOptions={load}
            isMulti={multiple}
            key={workspaceId}
            onCreate={onCreate}
        />
    );
}

export type AttributeEntityOption = {
    item: AttributeEntity;
} & SelectOption;
