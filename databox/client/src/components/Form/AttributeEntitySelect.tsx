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

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId?: string;
    multiple: IsMulti;
    allowNew?: boolean;
    type: string;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function AttributeEntitySelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({
    workspaceId: wsId,
    multiple,
    type,
    allowNew = true,
    ...rest
}: Props<TFieldValues, IsMulti>) {
    const {openModal} = useModals();
    const workspaceContext = React.useContext(WorkspaceContext);

    const workspaceId = wsId ?? workspaceContext?.workspaceId;
    if (!workspaceId) {
        throw new Error('Missing workspace context');
    }

    const onCreate: RSelectOnCreate | undefined = allowNew
        ? (inputValue, onCreate) => {
              openModal(CreateAttributeEntityDialog, {
                  value: inputValue,
                  type,
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
                workspace: workspaceId,
                type,
                query: inputValue,
            })
        ).result;

        return data.map((t: AttributeEntity) => ({
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
            onCreate={onCreate}
        />
    );
}

export type AttributeEntityOption = {
    item: AttributeEntity;
} & SelectOption;
