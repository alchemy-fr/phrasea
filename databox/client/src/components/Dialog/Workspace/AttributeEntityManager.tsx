import {AttributeEntity, EntityList} from '../../../types';
import {
    deleteAttributeEntity,
    getAttributeEntities,
    postAttributeEntity,
    putAttributeEntity,
} from '../../../api/attributeEntity';
import {ListItemText} from '@mui/material';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import AttributeEntityFields from '../../AttributeEntity/AttributeEntityFields.tsx';
import React from 'react';

function Item({
    usedFormSubmit,
    workspace,
}: DefinitionItemFormProps<AttributeEntity>) {
    return (
        <AttributeEntityFields
            usedFormSubmit={usedFormSubmit}
            workspace={workspace}
        />
    );
}

function ListItem({data}: DefinitionItemProps<AttributeEntity>) {
    return <ListItemText primary={data.value} />;
}

function createNewItem(): Partial<AttributeEntity> {
    return {
        value: '',
        translations: {},
    };
}

type Props = DefinitionItemManageProps<EntityList> &
    Omit<DataTabProps<EntityList>, 'onClose'>;

export default function AttributeEntityManager({
    data: list,
    minHeight,
    workspace,
    setSubManagementState,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributeEntity) => {
        if (data.id) {
            return await putAttributeEntity(data.id, data);
        } else {
            return await postAttributeEntity(list.id, {
                ...data,
            });
        }
    };

    return (
        <DefinitionManager
            deleteConfirmAssertions={() => [
                t(
                    'attribute_entity.delete.confirm.assertion.unset_on_attrs',
                    `I understand that this entity will be unset on all asset's attributes using it.`
                ),
            ]}
            managerFormId={'entity-attribute-manager'}
            itemComponent={Item}
            listComponent={ListItem}
            load={() =>
                getAttributeEntities({
                    list: list.id,
                }).then(r => r.result)
            }
            workspace={workspace}
            minHeight={minHeight}
            createNewItem={createNewItem}
            newLabel={t('attribute_entity.new.label', 'New Entity')}
            handleSave={handleSave}
            handleDelete={deleteAttributeEntity}
            setSubManagementState={setSubManagementState}
        />
    );
}
