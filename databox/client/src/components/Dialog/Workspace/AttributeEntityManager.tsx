import {AttributeEntity, EntityList} from '../../../types';
import {
    deleteAttributeEntity,
    formatAttributeEntityLabel,
    getAttributeEntities,
    postAttributeEntity,
    putAttributeEntity,
} from '../../../api/attributeEntity';
import {
    Badge,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionListItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import AttributeEntityFields from '../../AttributeEntity/AttributeEntityFields.tsx';
import React from 'react';
import {ContentCopy} from '@mui/icons-material';
import ExportAttributeEntitiesDialog from '../AttributeEntity/ExportAttributeEntitiesDialog.tsx';
import {useModals} from '@alchemy/navigation';
import {search} from '../../../lib/search.ts';
import ImportExportIcon from '@mui/icons-material/ImportExport';
import ImportAttributeEntitiesDialog from '../AttributeEntity/ImportAttributeEntitiesDialog.tsx';
import IconButton from '@mui/material/IconButton';
import DeleteIcon from '@mui/icons-material/Delete';
import FilterAltIcon from '@mui/icons-material/FilterAlt';
import SettingsIcon from '@mui/icons-material/Settings';
import {DropdownActions} from '@alchemy/phrasea-ui';

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

function EntityListItem({
    data,
    onDelete,
}: DefinitionListItemProps<AttributeEntity>) {
    return (
        <>
            <ListItemText
                primary={formatAttributeEntityLabel(data, {
                    noTranslate: true,
                })}
            />
            {onDelete ? (
                <ListItemSecondaryAction>
                    <IconButton
                        edge="end"
                        aria-label="delete"
                        color={'error'}
                        onClick={e => {
                            e.stopPropagation();
                            onDelete();
                        }}
                    >
                        <DeleteIcon />
                    </IconButton>
                </ListItemSecondaryAction>
            ) : null}
        </>
    );
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
            batchDelete={true}
            settingsNode={({items, reload}) => (
                <DropdownActions
                    anchorOrigin={{
                        vertical: 'bottom',
                        horizontal: 'left',
                    }}
                    mainButton={props => (
                        <IconButton {...props}>
                            <SettingsIcon />
                        </IconButton>
                    )}
                    children={closeWrapper => [
                        <ListItem disablePadding key={'export'}>
                            <ListItemButton
                                onClick={closeWrapper(() => {
                                    if (items) {
                                        openModal(
                                            ExportAttributeEntitiesDialog,
                                            {
                                                list: items,
                                                locales:
                                                    workspace.enabledLocales ??
                                                    [],
                                            }
                                        );
                                    }
                                })}
                                disabled={!items}
                            >
                                <ListItemIcon>
                                    <ContentCopy />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t(
                                        'entity_type.list.export',
                                        'Export'
                                    )}
                                />
                            </ListItemButton>
                        </ListItem>,
                        <ListItem disablePadding key={'import'}>
                            <ListItemButton
                                onClick={closeWrapper(() => {
                                    if (items) {
                                        openModal(
                                            ImportAttributeEntitiesDialog,
                                            {
                                                list,
                                                onSuccess: () => {
                                                    reload();
                                                },
                                            }
                                        );
                                    }
                                })}
                                disabled={!items}
                            >
                                <ListItemIcon>
                                    <ImportExportIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t(
                                        'entity_type.list.import',
                                        'Import'
                                    )}
                                />
                            </ListItemButton>
                        </ListItem>,
                    ]}
                />
            )}
            deleteConfirmAssertions={() => [
                t(
                    'attribute_entity.delete.confirm.assertion.unset_on_attrs',
                    `I understand that this entity will be unset on all asset's attributes using it.`
                ),
            ]}
            searchFilter={({items}, value) =>
                search<AttributeEntity>(items!, ['value'], value)
            }
            managerFormId={'entity-attribute-manager'}
            itemComponent={Item}
            listComponent={EntityListItem}
            load={({nextUrl, query}) =>
                getAttributeEntities({
                    nextUrl,
                    list: list.id,
                    value: query,
                })
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
