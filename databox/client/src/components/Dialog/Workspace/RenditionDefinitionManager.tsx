import {RenditionClass, RenditionDefinition, Workspace} from '../../../types';
import {FormGroup, FormHelperText, FormLabel, ListItemText, TextField} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
    OnSort,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import {FormFieldErrors} from '@alchemy/react-form';
import {
    deleteRenditionDefinition,
    getWorkspaceRenditionDefinitions,
    postRenditionDefinition,
    putRenditionDefinition,
} from '../../../api/rendition';
import RenditionClassSelect from '../../Form/RenditionClassSelect';
import {CheckboxWidget} from '@alchemy/react-form';
import apiClient from '../../../api/api-client';
import {toast} from 'react-toastify';
import React from 'react';
import RenditionDefinitionSelect from "../../Form/RenditionDefinitionSelect.tsx";
import CodeEditorWidget from "../../Form/CodeEditorWidget.tsx";

function Item({
    data,
    usedFormSubmit: {
        submitting,
        register,
        control,
        reset,
        watch,
        formState: {errors},
    },
    workspace,
}: DefinitionItemFormProps<RenditionDefinition>) {
    const {t} = useTranslation();

    React.useEffect(() => {
        reset(normalizeData(data));
    }, [data]);

    const pickSourceFile = watch('pickSourceFile');

    return (
        <>
            <FormRow>
                <TextField
                    label={t('form.rendition_definition.name.label', 'Name')}
                    {...register('name')}
                    disabled={submitting}
                />
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
                <FormGroup>
                    <CheckboxWidget
                        label={t(
                            'form.rendition_definition.useAsOriginal.label',
                            'Use as original'
                        )}
                        disabled={submitting}
                        name={'useAsOriginal'}
                        control={control}
                    />
                    <FormFieldErrors field={'useAsOriginal'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <CheckboxWidget
                        label={t(
                            'form.rendition_definition.useAsPreview.label',
                            'Use as preview'
                        )}
                        disabled={submitting}
                        name={'useAsPreview'}
                        control={control}
                    />
                    <FormFieldErrors field={'useAsPreview'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <CheckboxWidget
                        label={t(
                            'form.rendition_definition.useAsThumbnail.label',
                            'Use as thumbnail'
                        )}
                        disabled={submitting}
                        name={'useAsThumbnail'}
                        control={control}
                    />
                    <FormFieldErrors field={'useAsThumbnail'} errors={errors} />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <CheckboxWidget
                        label={t(
                            'form.rendition_definition.useAsThumbnailActive.label',
                            'Use as active thumbnail'
                        )}
                        disabled={submitting}
                        name={'useAsThumbnailActive'}
                        control={control}
                    />
                    <FormFieldErrors
                        field={'useAsThumbnailActive'}
                        errors={errors}
                    />
                </FormGroup>
            </FormRow>
            <FormRow>
                <FormGroup>
                    <CheckboxWidget
                        label={t(
                            'form.rendition_definition.pickSourceFile.label',
                            'Pick source file'
                        )}
                        disabled={submitting}
                        name={'pickSourceFile'}
                        control={control}
                    />
                    <FormFieldErrors field={'pickSourceFile'} errors={errors} />
                </FormGroup>
            </FormRow>
            {!pickSourceFile ? <>
                <FormRow>
                    <CodeEditorWidget
                        control={control}
                        label={t('form.rendition_definition.definition.label', 'Build definition')}
                        name={'definition'}
                        disabled={submitting}
                        mode={'yaml'}
                    />
                    <FormFieldErrors field={'definition'} errors={errors} />
                </FormRow>
            </> : ''}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<RenditionDefinition>) {
    return <ListItemText primary={data.name} />;
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<RenditionDefinition> {
    return {
        name: '',
        pickSourceFile: false,
        useAsOriginal: false,
        useAsPreview: false,
        useAsThumbnail: false,
        useAsThumbnailActive: false,
        class: null,
    };
}

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
        />
    );
}

function normalizeData(data: RenditionDefinition) {
    return {
        ...data,
        class: data.class ? (data.class as RenditionClass)['@id'] : null,
    };
}
