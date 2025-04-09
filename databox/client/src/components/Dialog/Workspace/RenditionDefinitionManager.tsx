import {RenditionClass, RenditionDefinition, Workspace} from '../../../types';
import {
    FormGroup,
    FormHelperText,
    FormLabel,
    ListItemText,
    TextField,
} from '@mui/material';
import {
    FormFieldErrors,
    FormRow,
    RSelectWidget,
    TranslatedField,
} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
    OnSort,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {
    deleteRenditionDefinition,
    getWorkspaceRenditionDefinitions,
    postRenditionDefinition,
    putRenditionDefinition,
    RenditionBuildMode,
} from '../../../api/rendition';
import RenditionClassSelect from '../../Form/RenditionClassSelect';
import apiClient from '../../../api/api-client';
import {toast} from 'react-toastify';
import React from 'react';
import RenditionDefinitionSelect from '../../Form/RenditionDefinitionSelect.tsx';
import CodeEditorWidget from '../../Form/CodeEditorWidget.tsx';
import UseAsWidget from '../../Form/UseAsWidget.tsx';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import {useCreateSaveTranslations} from '../../../hooks/useCreateSaveTranslations.ts';

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
        putFn: async (id, d) => {
            const r = await onSave({
                id,
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

    return (
        <>
            <FormRow>
                <TranslatedField<RenditionDefinition>
                    field={'name'}
                    getData={getValues}
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
                        {t('form.rendition_definition.class.label', 'Class')}
                    </FormLabel>
                    <RenditionClassSelect
                        disabled={submitting}
                        name={'class'}
                        control={control}
                        workspaceId={workspace.id}
                    />
                    <FormFieldErrors field={'class'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t('form.rendition_definition.parent.label', 'Parent')}
                    </FormLabel>
                    <RenditionDefinitionSelect
                        disabled={submitting}
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
                    </FormRow>
                </>
            ) : (
                ''
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<RenditionDefinition>) {
    return <ListItemText primary={data.nameTranslated} />;
}

function createNewItem(): Partial<RenditionDefinition> {
    return {
        name: '',
        buildMode: RenditionBuildMode.PICK_SOURCE,
        useAsOriginal: false,
        useAsPreview: false,
        useAsThumbnail: false,
        useAsThumbnailActive: false,
        class: null,
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
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getWorkspaceRenditionDefinitions(workspace.id)}
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
        class:
            typeof data.class === 'string'
                ? data.class
                : data.class
                  ? (data.class as RenditionClass)['@id']
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
