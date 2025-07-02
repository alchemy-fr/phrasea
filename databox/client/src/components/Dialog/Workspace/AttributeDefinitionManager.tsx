import React from 'react';
import {
    AttributePolicy,
    AttributeDefinition,
    EntityList,
    Workspace,
} from '../../../types';
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
import {
    CheckboxWidget,
    FormFieldErrors,
    FormRow,
    TranslatedField,
} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
    OnSort,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import AttributePolicySelect from '../../Form/AttributePolicySelect';
import FieldTypeSelect from '../../Form/FieldTypeSelect';
import {fieldTypesIcons} from '../../../lib/icons';
import apiClient from '../../../api/api-client';
import {toast} from 'react-toastify';
import CodeEditorWidget from '../../Form/CodeEditorWidget.tsx';
import ObjectTranslationField from '../../Form/ObjectTranslationField.tsx';
import LastErrorsList from './LastErrorsList.tsx';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import {useCreateSaveTranslations} from '../../../hooks/useCreateSaveTranslations.ts';
import {useAttributeDefinitionStore} from '../../../store/attributeDefinitionStore.ts';
import EntityListSelect from '../../Form/EntityListSelect.tsx';
import {NO_LOCALE} from '../../Media/Asset/Attribute/constants.ts';
import {AttributeType} from '../../../api/types.ts';

function Item({
    usedFormSubmit,
    workspace,
    data,
    onSave,
    onItemUpdate,
}: DefinitionItemFormProps<AttributeDefinition>) {
    const {t} = useTranslation();

    const isNew = !data.id;

    const {
        register,
        submitting,
        control,
        watch,
        setValue,
        getValues,
        formState: {errors},
    } = usedFormSubmit;

    const createSaveTranslations = useCreateSaveTranslations({
        data,
        setValue,
        putFn: async (_id, d) => {
            const r = await onSave({
                ...getValues(),
                ...d,
            } as AttributeDefinition);
            onItemUpdate(r);

            return r;
        },
    });

    const fieldType = watch('fieldType');
    const translatable = watch('translatable');

    return (
        <>
            <LastErrorsList data={data} />
            <FormRow>
                <TranslatedField<AttributeDefinition>
                    field={'name'}
                    getData={getValues}
                    title={t(
                        'form.attribute_definition.name.translate.title',
                        'Translate Name'
                    )}
                    onUpdate={createSaveTranslations('name')}
                >
                    <TextField
                        label={t(
                            'form.attribute_definition.name.label',
                            'Name'
                        )}
                        disabled={submitting}
                        {...register('name', {
                            required: true,
                        })}
                    />
                </TranslatedField>
                <FormFieldErrors field={'name'} errors={errors} />
            </FormRow>
            {!isNew && (
                <FormRow>
                    <TextField
                        label={t(
                            'form.attribute_definition.slug.label',
                            'Slug'
                        )}
                        {...register('slug')}
                        disabled={submitting}
                        inputProps={{
                            readOnly: true,
                        }}
                    />
                    <FormFieldErrors field={'slug'} errors={errors} />
                </FormRow>
            )}
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
                    <FormFieldErrors field={'fieldType'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.enabled.label',
                        'Enabled'
                    )}
                    control={control}
                    name={'enabled'}
                    disabled={submitting}
                />
            </FormRow>
            {fieldType === AttributeType.Entity ? (
                <FormRow>
                    <FormLabel>
                        {t(
                            'form.attribute_definition.entityList.label',
                            'Entity List'
                        )}
                    </FormLabel>
                    <EntityListSelect
                        useIRI={true}
                        disabled={submitting}
                        name={'entityList'}
                        control={control}
                        workspaceId={workspace.id}
                    />
                    <FormFieldErrors field={'entityList'} errors={errors} />
                </FormRow>
            ) : (
                ''
            )}
            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t('form.attribute_definition.policy.label', 'Policy')}
                    </FormLabel>
                    <AttributePolicySelect
                        disabled={submitting}
                        name={'policy'}
                        control={control}
                        workspaceId={workspace.id}
                    />
                    <FormFieldErrors field={'policy'} errors={errors} />
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
                        'form.attribute_definition.editable.label',
                        'Editable'
                    )}
                    control={control}
                    name={'editable'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'editable'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.editableInGui.label',
                        'Editable in GUI'
                    )}
                    control={control}
                    name={'editableInGui'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'editableInGui'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.sortable.label',
                        'Sortable'
                    )}
                    control={control}
                    name={'sortable'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'sortable'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.suggest.label',
                        'Display in search suggestions'
                    )}
                    control={control}
                    name={'suggest'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'suggest'} errors={errors} />
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
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.facetEnabled.label',
                        'Facets'
                    )}
                    control={control}
                    name={'facetEnabled'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'facetEnabled'} errors={errors} />
            </FormRow>
            <FormRow>
                <ObjectTranslationField
                    translatable={translatable}
                    displayNoLocale={true}
                    label={t(
                        'form.attribute_definition.fallback.label',
                        'Fallback'
                    )}
                    locales={workspace.enabledLocales ?? []}
                    field={({locale}) => {
                        return (
                            <CodeEditorWidget
                                control={control}
                                name={`fallback.${locale ?? NO_LOCALE}`}
                                disabled={submitting}
                                mode={'twig'}
                                height={'200px'}
                            />
                        );
                    }}
                />
                <FormFieldErrors field={'fallback'} errors={errors} />
            </FormRow>
            <FormRow>
                <ObjectTranslationField
                    translatable={translatable}
                    displayNoLocale={true}
                    label={t(
                        'form.attribute_definition.initialValues.label',
                        'Initial Values'
                    )}
                    locales={workspace.enabledLocales ?? []}
                    field={({locale}) => {
                        return (
                            <CodeEditorWidget
                                control={control}
                                name={`initialValues.${locale ?? NO_LOCALE}`}
                                disabled={submitting}
                                mode={'twig'}
                                height={'200px'}
                            />
                        );
                    }}
                />
                <FormFieldErrors field={'initialValues'} errors={errors} />
            </FormRow>
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AttributeDefinition>) {
    return (
        <>
            <ListItemIcon>
                {React.createElement(
                    fieldTypesIcons[data.fieldType || AttributeType.Text] ??
                        fieldTypesIcons.text
                )}
            </ListItemIcon>
            <ListItemText
                primary={data.nameTranslated ?? data.name}
                primaryTypographyProps={{
                    color: data.enabled ? undefined : 'error',
                }}
                secondary={data.fieldType}
            />
        </>
    );
}

function createNewItem(): Partial<AttributeDefinition> {
    return {
        name: '',
        slug: '',
        multiple: false,
        translatable: false,
        allowInvalid: false,
        searchable: true,
        sortable: false,
        suggest: false,
        editable: true,
        editableInGui: true,
        fieldType: AttributeType.Text,
        policy: null,
        entityList: null,
        enabled: true,
    };
}

type Props = DataTabProps<Workspace>;

export default function AttributeDefinitionManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const {addDefinition, updateDefinition} = useAttributeDefinitionStore(
        s => ({
            addDefinition: s.addDefinition,
            updateDefinition: s.updateDefinition,
        })
    );

    const handleSave = async (data: AttributeDefinition) => {
        if (data.id) {
            const d = await putAttributeDefinition(data.id, data);
            updateDefinition(d);

            return d;
        } else {
            const d = await postAttributeDefinition({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });

            addDefinition(d);

            return d;
        }
    };

    const onSort: OnSort = async ids => {
        await apiClient.post(`/attribute-definitions/sort`, ids);
        toast.success(t('common.item_sorted', 'Order saved!') as string);
    };

    return (
        <DefinitionManager
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getWorkspaceAttributeDefinitions(workspace.id)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('attribute_definitions.new.label', 'New attribute')}
            handleSave={handleSave}
            handleDelete={deleteAttributeDefinition}
            onSort={onSort}
            normalizeData={normalizeData}
        />
    );
}

function normalizeData(data: AttributeDefinition) {
    return {
        ...data,
        policy:
            typeof data.policy === 'string'
                ? data.policy
                : data.policy
                  ? (data.policy as AttributePolicy)['@id']
                  : null,
        entityList: data.entityList
            ? (data.entityList as EntityList)['@id']
            : null,
    };
}
