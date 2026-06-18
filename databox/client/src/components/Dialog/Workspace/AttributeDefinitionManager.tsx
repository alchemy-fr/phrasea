import React, {useState} from 'react';
import {
    AssetType,
    AssetTypeFilter,
    AttributeDefinition,
    AttributePolicy,
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
    Box,
    FormControlLabel,
    FormGroup,
    FormHelperText,
    FormLabel,
    ListItemIcon,
    ListItemText,
    Switch,
    TextField,
} from '@mui/material';
import {
    CheckboxWidget,
    FormFieldErrors,
    FormRow,
    ResolvedChangedValue,
    SelectOption,
    TranslatedField,
} from '@alchemy/react-form';
import DefinitionManager from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import AttributePolicySelect from '../../Form/AttributePolicySelect';
import FieldTypeSelect from '../../Form/FieldTypeSelect';
import {typesIcons} from '../../../lib/icons';
import {toast} from 'react-toastify';
import CodeEditorWidget from '../../Form/CodeEditor/CodeEditorWidget.tsx';
import ObjectTranslationField from '../../Form/ObjectTranslationField.tsx';
import LastErrorsList from './LastErrorsList.tsx';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import {useCreateSaveTranslations} from '../../../hooks/useCreateSaveTranslations.ts';
import {useAttributeDefinitionStore} from '../../../store/attributeDefinitionStore.ts';
import EntityListSelect from '../../Form/EntityListSelect.tsx';
import {NO_LOCALE} from '../../Media/Asset/Attribute/constants.ts';
import {AttributeType} from '../../../api/types.ts';
import {getLocaleOptions} from '../../../api/locale.ts';
import AssetTypeSelect from '../../Form/AssetTypeSelect.tsx';
import {search} from '../../../lib/search.ts';
import AssetTypeFilterSelect, {
    denormalizeAssetTypeFilterValue,
} from '../../Form/AssetTypeFilterSelect.tsx';
import {apiClient} from '../../../init.ts';
import {
    DefinitionItemFormProps,
    DefinitionItemProps,
    OnSort,
} from './DefinitionManager/managerTypes.ts';
import {isNotNull} from '@alchemy/core';
import BooleanFilterSelect from '../../Form/BooleanFilterSelect.tsx';
import TwigEditorWidget from '../../Form/CodeEditor/TwigEditorWidget.tsx';

function Item({
    usedFormSubmit,
    workspace,
    data,
    onSave,
    onItemUpdate,
}: DefinitionItemFormProps<AttributeDefinition>) {
    const {t} = useTranslation();
    const [useAsName, setUseAsName] = useState<boolean>(
        isNotNull(data.namePriority)
    );

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

    React.useEffect(() => {
        setUseAsName(isNotNull(data.namePriority));
    }, [data]);

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

    const type = watch('type');
    const translatable = watch('translatable');

    return (
        <>
            <LastErrorsList data={data} />
            <FormRow>
                <TranslatedField<AttributeDefinition>
                    field={'name'}
                    getLocales={getLocaleOptions}
                    locales={workspace.enabledLocales}
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
                        name={'type'}
                        control={control}
                    />
                    <FormFieldErrors field={'type'} errors={errors} />
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
            {type === AttributeType.Entity ? (
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
                <AssetTypeSelect
                    control={control}
                    name={'target'}
                    required={true}
                    disabled={submitting}
                    label={t(
                        'form.attribute_definition.asset_type.label',
                        'Asset Type Target'
                    )}
                />
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
                            <TwigEditorWidget
                                control={control}
                                name={`fallback.${locale ?? NO_LOCALE}`}
                                disabled={submitting}
                                height={'200px'}
                            />
                        );
                    }}
                />
                <FormFieldErrors
                    field={'fallback'}
                    errors={errors}
                    hasTranslations={true}
                />
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

            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.attribute_definition.fillFromName.label',
                        'Fill From name'
                    )}
                    control={control}
                    name={'fillFromName'}
                    disabled={submitting}
                />
                <FormHelperText>
                    {t(
                        'form.attribute_definition.fillFromName.helper',
                        'Automatically fill this attribute with the value of the name field. Only applies to text attributes.'
                    )}
                </FormHelperText>
                <FormFieldErrors field={'fillFromName'} errors={errors} />
            </FormRow>

            <FormRow>
                <FormControlLabel
                    control={
                        <Switch
                            checked={useAsName}
                            onChange={() => {
                                setUseAsName(!useAsName);
                                setValue('namePriority', useAsName ? null : 0);
                            }}
                            disabled={submitting}
                        />
                    }
                    label={t(
                        'form.attribute_definition.useAsName.label',
                        'Use as Asset Name'
                    )}
                />
                {useAsName && (
                    <TextField
                        type={'number'}
                        label={t(
                            'form.attribute_definition.namePriority.label',
                            'Name priority'
                        )}
                        {...register('namePriority')}
                        disabled={submitting}
                    />
                )}
                <FormHelperText>
                    {t(
                        'form.attribute_definition.namePriority.helper',
                        'Use this attribute as name when displaying the asset. Only applies to text attributes.'
                    )}
                </FormHelperText>
                <FormFieldErrors field={'namePriority'} errors={errors} />
            </FormRow>
        </>
    );
}

function ListItem({data}: DefinitionItemProps<AttributeDefinition>) {
    return (
        <>
            <ListItemIcon>
                {React.createElement(
                    typesIcons[data.type || AttributeType.Text] ??
                        typesIcons.text
                )}
            </ListItemIcon>
            <ListItemText
                primary={data.displayName ?? data.name}
                primaryTypographyProps={{
                    color: data.enabled ? undefined : 'error',
                }}
                secondary={data.type}
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
        type: AttributeType.Text,
        policy: null,
        entityList: null,
        enabled: true,
        target: AssetType.Both,
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
            searchFilter={({items}, value) =>
                search<AttributeDefinition>(
                    items,
                    ['displayName', 'name'],
                    value
                )
            }
            applyFilters={(list, {type, target, fillFromName, useAsName}) =>
                list.filter(ad => {
                    let r = true;
                    if (type) {
                        r = r && ad.type === type;
                    }
                    if (target) {
                        r = r && (target & ad.target) === target;
                    }

                    if (isNotNull(fillFromName)) {
                        r = r && ad.fillFromName === fillFromName;
                    }
                    if (isNotNull(useAsName)) {
                        r =
                            r &&
                            (useAsName
                                ? isNotNull(ad.namePriority)
                                : !isNotNull(ad.namePriority));
                    }

                    return r;
                })
            }
            filters={({filters, setFilter}) => (
                <Box
                    sx={{
                        p: 1,
                    }}
                >
                    <AssetTypeFilterSelect
                        label={t(
                            'attribute_definitions.filter.asset_type',
                            'Filter by Asset Type'
                        )}
                        value={filters.target as any}
                        onChange={(newValue: ResolvedChangedValue<false>) =>
                            setFilter(
                                'target',
                                denormalizeAssetTypeFilterValue(
                                    (newValue as SelectOption)?.value
                                ) as unknown as AssetTypeFilter
                            )
                        }
                    />
                    <FieldTypeSelect
                        label={t(
                            'attribute_definitions.filter.type',
                            'Filter by Type'
                        )}
                        value={filters.type as any}
                        onChange={newValue =>
                            setFilter(
                                'type',
                                (newValue as SelectOption)
                                    ?.value as AttributeType | null
                            )
                        }
                    />
                    <BooleanFilterSelect
                        label={t(
                            'attribute_definitions.filter.fillFromName',
                            'Fill from Name'
                        )}
                        value={filters.fillFromName as any}
                        onChange={(newValue: ResolvedChangedValue<false>) => {
                            setFilter('fillFromName', newValue?.value);
                        }}
                    />
                    <BooleanFilterSelect
                        label={t(
                            'attribute_definitions.filter.useAsName',
                            'Use as Name'
                        )}
                        value={filters.useAsName as any}
                        onChange={(newValue: ResolvedChangedValue<false>) => {
                            setFilter('useAsName', newValue?.value);
                        }}
                    />
                </Box>
            )}
            itemComponent={Item}
            listComponent={ListItem}
            load={({query, nextUrl, filters: {type, target}}) =>
                getWorkspaceAttributeDefinitions({
                    workspaceId: workspace.id,
                    query,
                    nextUrl,
                    type,
                    target: target ?? AssetTypeFilter.All,
                })
            }
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('attribute_definitions.new.label', 'New attribute')}
            handleSave={handleSave}
            handleDelete={deleteAttributeDefinition}
            onSort={onSort}
            normalizeData={normalizeData}
            denormalizeData={denormalizeData}
        />
    );
}

function denormalizeData(data: AttributeDefinition): AttributeDefinition {
    return {
        ...data,
        namePriority: isNotNull(data.namePriority)
            ? parseInt(data.namePriority as unknown as string)
            : null,
    };
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
