import {EntityList, Workspace} from '../../../types';
import {
    deleteEntityList,
    getEntityLists,
    postEntityList,
    putEntityList,
} from '../../../api/entityList.ts';
import {ListItemSecondaryAction, ListItemText, TextField} from '@mui/material';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import DefinitionManager from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import AttributeEntityManager from './AttributeEntityManager.tsx';
import IconButton from '@mui/material/IconButton';
import EditIcon from '@mui/icons-material/Edit';
import React from 'react';
import {search} from '../../../lib/search.ts';
import {
    DefinitionItemFormProps,
    DefinitionItemManageProps,
    DefinitionListItemProps,
} from './DefinitionManager/managerTypes.ts';

function Item({usedFormSubmit}: DefinitionItemFormProps<EntityList>) {
    const {t} = useTranslation();
    const {
        control,
        register,
        submitting,
        formState: {errors},
    } = usedFormSubmit;

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.entity_type.name.label', 'Type')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'allowNewValues'}
                    label={t(
                        'form.entity_type.allowNewValues.label',
                        'Accept new values'
                    )}
                    disabled={submitting}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'approveNewValues'}
                    label={t(
                        'form.entity_type.approveNewValues.label',
                        'Automatically approve new values'
                    )}
                    disabled={submitting}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'withTranslations'}
                    label={t(
                        'form.entity_type.withTranslations.label',
                        'Use translations'
                    )}
                    disabled={submitting}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'withSynonyms'}
                    label={t(
                        'form.entity_type.withSynonyms.label',
                        'Use synonyms'
                    )}
                    disabled={submitting}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'withEmojis'}
                    label={t(
                        'form.entity_type.withEmojis.label',
                        'Use emojis as icon'
                    )}
                    disabled={submitting}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'withColors'}
                    label={t('form.entity_type.withColors.label', 'Use colors')}
                    disabled={submitting}
                />
            </FormRow>
        </>
    );
}

function ManageItem(props: DefinitionItemManageProps<EntityList>) {
    return <AttributeEntityManager {...props} />;
}

function ListItem({data, onEdit}: DefinitionListItemProps<EntityList>) {
    const {t} = useTranslation();

    return (
        <>
            <ListItemText
                primary={data.name}
                secondary={
                    data.definitions.length > 0
                        ? t('entity_type.definitions.count', {
                              defaultValue:
                                  'Used on {{ count }} attribute definition',
                              defaultValue_other:
                                  'Used on {{ count }} attribute definitions',
                              count: data.definitions.length,
                          })
                        : t(
                              'entity_type.definitions.none',
                              'No attribute definition usage'
                          )
                }
            />
            <ListItemSecondaryAction>
                <IconButton
                    onClick={e => {
                        e.stopPropagation();
                        e.preventDefault();
                        onEdit();
                    }}
                >
                    <EditIcon />
                </IconButton>
            </ListItemSecondaryAction>
        </>
    );
}

function createNewItem(): Partial<EntityList> {
    return {
        name: '',
    };
}

type Props = DataTabProps<Workspace>;

export default function EntityListManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: EntityList) => {
        if (data.id) {
            return await putEntityList(data.id, data);
        } else {
            return await postEntityList(workspace.id, {
                ...data,
            });
        }
    };

    return (
        <DefinitionManager
            deleteConfirmAssertions={(data: EntityList) => {
                const assertions = [
                    t(
                        'entity_type.delete.confirm.assertion.unset_on_attrs',
                        `I understand that all entities of this list will be unset on asset's attributes using it.`
                    ),
                    t(
                        'entity_type.delete.confirm.assertion.no_undo',
                        'I understand this action cannot be undone.'
                    ),
                ];

                data.definitions.forEach(def => {
                    assertions.push(
                        t(
                            'attribute_entity.delete.confirm.assertion.delete_attr_def',
                            `I understand that attribute definition "{{ definitionName }}" will be deleted because it's using it.`,
                            {
                                definitionName: def.nameTranslated ?? def.name,
                            }
                        )
                    );
                });

                return assertions;
            }}
            searchFilter={({items}, value) =>
                search<EntityList>(items, ['name'], value)
            }
            itemComponent={Item}
            manageItemComponent={ManageItem}
            listComponent={ListItem}
            load={({nextUrl}) =>
                getEntityLists({
                    workspaceId: workspace.id,
                    nextUrl,
                })
            }
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('entity_type.new.label', 'New Entity List')}
            handleSave={handleSave}
            handleDelete={deleteEntityList}
        />
    );
}
