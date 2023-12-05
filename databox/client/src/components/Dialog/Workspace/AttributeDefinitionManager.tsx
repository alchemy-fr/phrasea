import React, {useEffect} from 'react';
import {AttributeClass, AttributeDefinition, Workspace} from '../../../types';
import {
    deleteAttributeDefinition,
    getWorkspaceAttributeDefinitions,
    postAttributeDefinition,
    putAttributeDefinition,
} from '../../../api/attributes';
import {
    FormGroup,
    FormLabel,
    ListItemIcon,
    ListItemText,
    TextField,
} from '@mui/material';
import FormRow from '../../Form/FormRow';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
    OnSort,
} from './DefinitionManager';
import {useTranslation} from 'react-i18next';
import FormFieldErrors from '../../Form/FormFieldErrors';
import CheckboxWidget from '../../Form/CheckboxWidget';
import AttributeClassSelect from '../../Form/AttributeClassSelect';
import FieldTypeSelect from '../../Form/FieldTypeSelect';
import {fieldTypesIcons} from '../../../lib/icons';
import apiClient from '../../../api/api-client';
import {toast} from 'react-toastify';

function Item({
    data,
    usedFormSubmit,
    workspaceId,
}: DefinitionItemFormProps<AttributeDefinition>) {
    const {t} = useTranslation();

    const {
        register,
        submitting,
        control,
        reset,
        formState: {errors},
    } = usedFormSubmit;

    useEffect(() => {
        reset(createData(data));
    }, [data]);

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.attribute_definition.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            <FormRow>
                <TextField
                    label={t('form.attribute_definition.slug.label', 'Slug')}
                    {...register('slug')}
                    disabled={submitting}
                    inputProps={{
                        readOnly: true,
                    }}
                />
                <FormFieldErrors field={'slug'} errors={errors} />
            </FormRow>
            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t(
                            'form.attribute_definition.field_type.label',
                            'Field type'
                        )}
                    </FormLabel>
                    <FieldTypeSelect
                        disabled={submitting}
                        name={'fieldType'}
                        control={control}
                    />
                    <FormFieldErrors field={'class'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t('form.attribute_definition.class.label', 'Class')}
                    </FormLabel>
                    <AttributeClassSelect
                        disabled={submitting}
                        name={'class'}
                        control={control}
                        workspaceId={workspaceId}
                    />
                    <FormFieldErrors field={'class'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.searchable.label',
                        'Searchable'
                    )}
                    control={control}
                    name={'searchable'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'searchable'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.translatable.label',
                        'Translatable'
                    )}
                    control={control}
                    name={'translatable'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'translatable'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.multiple.label',
                        'Multiple values'
                    )}
                    control={control}
                    name={'multiple'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'multiple'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.allowInvalid.label',
                        'Allow invalid values'
                    )}
                    control={control}
                    name={'allowInvalid'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'allowInvalid'} errors={errors} />
            </FormRow>
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AttributeDefinition>) {
    return (
        <>
            <ListItemIcon>
                {React.createElement(
                    fieldTypesIcons[data.fieldType || 'text'] ??
                        fieldTypesIcons.text
                )}
            </ListItemIcon>
            <ListItemText primary={data.name} secondary={data.fieldType} />
        </>
    );
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<AttributeDefinition> {
    return {
        name: '',
        multiple: false,
        translatable: false,
        allowInvalid: false,
        searchable: true,
        fieldType: 'text',
    };
}

export default function AttributeDefinitionManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributeDefinition) => {
        if (data.id) {
            return await putAttributeDefinition(data.id, data);
        } else {
            return await postAttributeDefinition({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    const onSort: OnSort = async ids => {
        await apiClient.put(`/attribute-definitions/sort`, ids);
        toast.success(t('common.item_sorted', 'Order saved!') as string);
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getWorkspaceAttributeDefinitions(workspace.id)}
            workspaceId={workspace.id}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('attribute_definitions.new.label', 'New attribute')}
            handleSave={handleSave}
            handleDelete={deleteAttributeDefinition}
            onSort={onSort}
        />
    );
}

function createData(data: AttributeDefinition) {
    return {
        ...data,
        class: data?.class && (data?.class as AttributeClass)['@id'],
    };
}
