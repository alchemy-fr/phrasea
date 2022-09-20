import React from 'react';
import {RenditionClass, RenditionDefinition, Workspace} from "../../../types";
import {FormGroup, FormLabel, ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemFormProps, DefinitionItemProps, OnSort} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import {useForm} from "react-hook-form";
import FormFieldErrors from "../../Form/FormFieldErrors";
import {
    deleteRenditionDefinition,
    getWorkspaceRenditionDefinitions,
    postRenditionDefinition,
    putRenditionDefinition
} from "../../../api/rendition";
import RenditionClassSelect from "../../Form/RenditionClassSelect";
import CheckboxWidget from "../../Form/CheckboxWidget";
import apiClient from "../../../api/api-client";
import {toast} from "react-toastify";
import {useDirtyFormPrompt} from "../Tabbed/FormTab";

function Item({
                  data,
                  handleSubmit: onSubmit,
                  formId,
                  submitting,
                  workspaceId,
              }: DefinitionItemFormProps<RenditionDefinition>) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: {errors, isDirty}
    } = useForm<any>({
        defaultValues: {
            ...data,
            class: data?.class && (data?.class as RenditionClass)['@id'],
        },
    });
    useDirtyFormPrompt(isDirty);

    return <form
        id={formId}
        onSubmit={handleSubmit(onSubmit(setError))}>
        <FormRow>
            <TextField
                label={t('form.rendition_definition.name.label', 'Name')}
                {...register('name')}
                disabled={submitting}
            />
            <FormFieldErrors
                field={'name'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <FormGroup>
                <FormLabel>{t('form.rendition_definition.class.label', 'Class')}</FormLabel>
                <RenditionClassSelect
                    disabled={submitting}
                    name={'class'}
                    control={control}
                    workspaceId={workspaceId}
                />
                <FormFieldErrors
                    field={'class'}
                    errors={errors}
                />
            </FormGroup>
        </FormRow>
        <FormRow>
            <FormGroup>
                <CheckboxWidget
                    label={t('form.rendition_definition.pickSourceFile.label', 'Pick source file')}
                    disabled={submitting}
                    name={'pickSourceFile'}
                    control={control}
                />
                <FormFieldErrors
                    field={'pickSourceFile'}
                    errors={errors}
                />
            </FormGroup>
        </FormRow>
        <FormRow>
            <FormGroup>
                <CheckboxWidget
                    label={t('form.rendition_definition.useAsOriginal.label', 'Use as original')}
                    disabled={submitting}
                    name={'useAsOriginal'}
                    control={control}
                />
                <FormFieldErrors
                    field={'useAsOriginal'}
                    errors={errors}
                />
            </FormGroup>
        </FormRow>
        <FormRow>
            <FormGroup>
                <CheckboxWidget
                    label={t('form.rendition_definition.useAsPreview.label', 'Use as preview')}
                    disabled={submitting}
                    name={'useAsPreview'}
                    control={control}
                />
                <FormFieldErrors
                    field={'useAsPreview'}
                    errors={errors}
                />
            </FormGroup>
        </FormRow>
        <FormRow>
            <FormGroup>
                <CheckboxWidget
                    label={t('form.rendition_definition.useAsThumbnail.label', 'Use as thumbnail')}
                    disabled={submitting}
                    name={'useAsThumbnail'}
                    control={control}
                />
                <FormFieldErrors
                    field={'useAsThumbnail'}
                    errors={errors}
                />
            </FormGroup>
        </FormRow>
        <FormRow>
            <FormGroup>
                <CheckboxWidget
                    label={t('form.rendition_definition.useAsThumbnailActive.label', 'Use as active thumbnail')}
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
    </form>
}

function ListItem({data}: DefinitionItemProps<RenditionDefinition>) {
    return <ListItemText
        primary={data.name}
    />
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<RenditionDefinition> {
    return {
        name: '',
    }
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
                workspace: `/workspaces/${workspace.id}`
            })
        }
    }

    const onSort: OnSort = async (ids) => {
        await apiClient.put(`/rendition-definitions/sort`, ids);

        toast.success(t('common.item_sorted', 'Order saved!'));
    }

    return <DefinitionManager
        itemComponent={Item}
        listComponent={ListItem}
        load={() => getWorkspaceRenditionDefinitions(workspace.id)}
        workspaceId={workspace.id}
        minHeight={minHeight}
        onClose={onClose}
        createNewItem={createNewItem}
        newLabel={t('rendition_definitions.new.label', 'New rendition')}
        handleSave={handleSave}
        handleDelete={deleteRenditionDefinition}
        onSort={onSort}
    />
}
