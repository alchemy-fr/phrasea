import {AttributeEntity, EntityList} from '../../../types';
import {
    deleteAttributeEntity,
    getAttributeEntities,
    postAttributeEntity,
    putAttributeEntity,
} from '../../../api/attributeEntity';
import {
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
} from '@mui/material';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import AttributeEntityFields from '../../AttributeEntity/AttributeEntityFields.tsx';
import React from 'react';
import {ContentCopy} from '@mui/icons-material';
import ExportAttributeEntitiesDialog from '../AttributeEntity/ExportAttributeEntitiesDialog.tsx';
import {useModals} from '@alchemy/navigation';

function Item({
    usedFormSubmit,
    workspace,
    data,
}: DefinitionItemFormProps<AttributeEntity>) {
    return (
        <AttributeEntityFields
            usedFormSubmit={usedFormSubmit}
            workspace={workspace}
            data={data}
        />
    );
}

function EntityListItem({data}: DefinitionItemProps<AttributeEntity>) {
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
    const {openModal} = useModals();

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
            preListBody={(list: AttributeEntity[] | undefined) => (
                <ListItem disablePadding>
                    <ListItemButton
                        onClick={() => {
                            if (list) {
                                openModal(ExportAttributeEntitiesDialog, {
                                    list: list,
                                    locales: workspace.enabledLocales ?? [],
                                });
                            }
                        }}
                        disabled={!list}
                    >
                        <ListItemIcon>
                            <ContentCopy />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('entity_type.list.export', 'Export')}
                        />
                    </ListItemButton>
                </ListItem>
            )}
            deleteConfirmAssertions={() => [
                t(
                    'attribute_entity.delete.confirm.assertion.unset_on_attrs',
                    `I understand that this entity will be unset on all asset's attributes using it.`
                ),
            ]}
            managerFormId={'entity-attribute-manager'}
            itemComponent={Item}
            listComponent={EntityListItem}
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
