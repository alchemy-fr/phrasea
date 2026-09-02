import {
    RenditionPolicy,
    RenditionDefinition,
    RenditionBuildModule,
    Workspace,
    AssetTypeFilter,
} from '../../../types';
import {
    Box,
    FormGroup,
    FormHelperText,
    FormLabel,
    ListItemText,
    TextField,
    Typography,
} from '@mui/material';
import {
    CheckboxWidget,
    FormFieldErrors,
    FormRow,
    ResolvedChangedValue,
    RSelectWidget,
    SelectOption,
    TranslatedField,
} from '@alchemy/react-form';
import DefinitionManager from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {
    deleteRenditionDefinition,
    getRenditionBuildModules,
    getWorkspaceRenditionDefinitions,
    postRenditionDefinition,
    putRenditionDefinition,
    RenditionBuildMode,
} from '../../../api/rendition';
import RenditionPolicySelect from '../../Form/RenditionPolicySelect';
import {toast} from 'react-toastify';
import React from 'react';
import RenditionDefinitionSelect from '../../Form/RenditionDefinitionSelect.tsx';
import CodeEditorWidget from '../../Form/CodeEditor/CodeEditorWidget.tsx';
import ReferenceSections from './ReferenceSections.tsx';
import UseAsWidget from '../../Form/UseAsWidget.tsx';
import MetadataValuesWidget from '../../Form/MetadataValuesWidget.tsx';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import {useCreateSaveTranslations} from '../../../hooks/useCreateSaveTranslations.ts';
import {getLocaleOptions} from '../../../api/locale.ts';
import AssetTypeSelect, {
    denormalizeValue,
} from '../../Form/AssetTypeSelect.tsx';
import {search} from '../../../lib/search.ts';
import AssetTypeFilterSelect from '../../Form/AssetTypeFilterSelect.tsx';
import {apiClient} from '../../../init.ts';
import {
    DefinitionItemFormProps,
    DefinitionItemProps,
    OnSort,
} from './DefinitionManager/managerTypes.ts';

function Item({
    data,
    onSave,
    onItemUpdate,
    usedFormSubmit: {
        submitting,
        register,
        control,
        reset,
        watch,
        setValue,
        getValues,
        formState: {errors},
    },
    workspace,
}: DefinitionItemFormProps<RenditionDefinition>) {
    const {t} = useTranslation();
    const createSaveTranslations = useCreateSaveTranslations({
        data,
        setValue,
        putFn: async (_id, d) => {
            const r = await onSave({
                ...denormalizeData(getValues()),
                ...d,
            } as RenditionDefinition);
            onItemUpdate(r);

            return r;
        },
    });

    React.useEffect(() => {
        reset(normalizeData(data));
    }, [data]);

    const buildMode = watch('buildMode');
    const [buildModules, setBuildModules] = React.useState<
        RenditionBuildModule[] | undefined
    >();

    React.useEffect(() => {
        if (
            buildMode === RenditionBuildMode.CUSTOM.toString() &&
            !buildModules
        ) {
            (async () => {
                const r = await getRenditionBuildModules();
                setBuildModules(r.result);
            })();
        }
    }, [buildMode, buildModules]);

    return (
        <>
            <FormRow>
                <TranslatedField<RenditionDefinition>
                    locales={workspace.enabledLocales}
                    field={'name'}
                    getData={getValues}
                    getLocales={getLocaleOptions}
                    title={t(
                        'form.rendition_definition.name.translate.title',
                        'Translate Name'
                    )}
                    onUpdate={createSaveTranslations('name')}
                >
                    <TextField
                        label={t(
                            'form.rendition_definition.name.label',
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
            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t('form.rendition_definition.policy.label', 'Policy')}
                    </FormLabel>
                    <RenditionPolicySelect
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
                        'form.rendition_definition.target.label',
                        'Asset Type Target'
                    )}
                />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.rendition_definition.substitutable.label',
                        'Substitutable'
                    )}
                    control={control}
                    name={'substitutable'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'substitutable'} errors={errors} />
            </FormRow>
            <FormRow>
                <CheckboxWidget
                    label={t(
                        'form.rendition_definition.write_metadata.label',
                        'Write attributes into rendition metadata on export'
                    )}
                    control={control}
                    name={'writeMetadata'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'writeMetadata'} errors={errors} />
            </FormRow>
            <FormRow>
                <FormGroup>
                    <MetadataValuesWidget
                        label={t(
                            'form.rendition_definition.metadata.label',
                            'Metadata to write'
                        )}
                        control={control}
                        name={'metadata'}
                        disabled={submitting}
                    />
                    <FormHelperText>
                        {t(
                            'form.rendition_definition.metadata.helper',
                            'Hardcoded metadata values written into the rendition file when it is exported.'
                        )}
                    </FormHelperText>
                    <FormFieldErrors field={'metadata'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t('form.rendition_definition.parent.label', 'Parent')}
                    </FormLabel>
                    <RenditionDefinitionSelect
                        disabled={submitting}
                        useIRI={true}
                        name={'parent'}
                        control={control}
                        workspaceId={workspace.id}
                        disabledValues={[`/rendition-definitions/${data.id}`]}
                        placeholder={t(
                            'form.rendition_definition.parent.placeholder',
                            'Asset source file'
                        )}
                    />
                    <FormHelperText>
                        {t(
                            'form.rendition_definition.parent.helper',
                            'Rendition from which this one is derived'
                        )}
                    </FormHelperText>
                    <FormFieldErrors field={'parent'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <UseAsWidget getValues={getValues} setValue={setValue} />
            </FormRow>
            <FormRow>
                <RSelectWidget
                    control={control}
                    name={'buildMode'}
                    label={t(
                        'form.rendition_definition.buildMode.label',
                        'Build Mode'
                    )}
                    options={[
                        {
                            label: t(
                                'rendition_definition.build_mode.none',
                                'None'
                            ),
                            value: RenditionBuildMode.NONE.toString(),
                        },
                        {
                            label: t(
                                'rendition_definition.build_mode.pick_source',
                                'Copy parent or source file'
                            ),
                            value: RenditionBuildMode.PICK_SOURCE.toString(),
                        },
                        {
                            label: t(
                                'rendition_definition.build_mode.custom',
                                'Build'
                            ),
                            value: RenditionBuildMode.CUSTOM.toString(),
                        },
                    ]}
                />
            </FormRow>
            {buildMode === RenditionBuildMode.CUSTOM.toString() ? (
                <>
                    <FormRow>
                        <CodeEditorWidget
                            control={control}
                            label={t(
                                'form.rendition_definition.definition.label',
                                'Build definition'
                            )}
                            name={'definition'}
                            disabled={submitting}
                            mode={'yaml'}
                            height={'700px'}
                        />
                        <FormFieldErrors field={'definition'} errors={errors} />
                        {buildModules ? (
                            <Box sx={{mt: 2}}>
                                <Typography variant={'body1'} sx={{mb: 1}}>
                                    {t(
                                        'form.rendition_definition.help.modules_reference',
                                        'Available modules reference:'
                                    )}
                                </Typography>
                                <ReferenceSections sections={buildModules} />
                            </Box>
                        ) : null}
                    </FormRow>
                </>
            ) : (
                ''
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<RenditionDefinition>) {
    return <ListItemText primary={data.displayName} />;
}

function createNewItem(): Partial<RenditionDefinition> {
    return {
        name: '',
        buildMode: RenditionBuildMode.PICK_SOURCE,
        substitutable: true,
        writeMetadata: true,
        metadata: {},
        useAsMain: false,
        useAsPreview: false,
        useAsThumbnail: false,
        useAsAnimatedThumbnail: false,
        policy: null,
    };
}

type Props = DataTabProps<Workspace>;

export default function RenditionDefinitionManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: RenditionDefinition) => {
        if (data.id) {
            return await putRenditionDefinition(data.id, data);
        } else {
            return await postRenditionDefinition({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    const onSort: OnSort = async ids => {
        await apiClient.post(`/rendition-definitions/sort`, ids);

        toast.success(t('common.item_sorted', 'Order saved!') as string);
    };

    return (
        <DefinitionManager
            searchFilter={({items}, value) =>
                search<RenditionDefinition>(
                    items,
                    ['displayName', 'name'],
                    value
                )
            }
            applyFilters={(list, {target}) =>
                list.filter(rd => {
                    return !target || (target & rd.target) === target;
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
                            'rendition_definitions.filter.asset_type',
                            'Filter by Asset Type'
                        )}
                        value={filters.target as any}
                        onChange={(newValue: ResolvedChangedValue<false>) =>
                            setFilter(
                                'target',
                                denormalizeValue(
                                    (newValue as SelectOption)?.value
                                ) as unknown as AssetTypeFilter
                            )
                        }
                    />
                </Box>
            )}
            itemComponent={Item}
            listComponent={ListItem}
            load={({query, nextUrl, filters: {target}}) =>
                getWorkspaceRenditionDefinitions({
                    workspaceId: workspace.id,
                    nextUrl,
                    query,
                    target,
                })
            }
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('rendition_definitions.new.label', 'New rendition')}
            handleSave={handleSave}
            handleDelete={deleteRenditionDefinition}
            onSort={onSort}
            normalizeData={normalizeData}
            denormalizeData={denormalizeData}
        />
    );
}

function normalizeData(data: RenditionDefinition) {
    return {
        ...data,
        buildMode: data.buildMode?.toString(),
        policy:
            typeof data.policy === 'string'
                ? data.policy
                : data.policy
                  ? (data.policy as RenditionPolicy)['@id']
                  : null,
        parent:
            typeof data.parent === 'string'
                ? data.parent
                : data.parent
                  ? (data.parent as RenditionDefinition)['@id']
                  : null,
    };
}

function denormalizeData(data: RenditionDefinition) {
    return {
        ...data,
        buildMode:
            typeof data.buildMode === 'string'
                ? parseInt(data.buildMode)
                : data.buildMode,
    };
}
