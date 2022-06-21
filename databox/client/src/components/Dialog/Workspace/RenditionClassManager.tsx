import React from 'react';
import {RenditionClass, Workspace} from "../../../types";
import {ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemFormProps, DefinitionItemProps} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import {useForm} from "react-hook-form";
import FormFieldErrors from "../../Form/FormFieldErrors";
import {deleteRenditionClass, getRenditionClasses, postRenditionClass, putRenditionClass} from "../../../api/rendition";

function Item({
                  data,
                  handleSubmit: onSubmit,
                  formId,
                  submitting,
              }: DefinitionItemFormProps<RenditionClass>) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: {errors}
    } = useForm<any>({
        defaultValues: data,
    });

    return <form
        id={formId}
        onSubmit={handleSubmit(onSubmit(setError))}>
        <FormRow>
            <TextField
                label={t('form.rendition_class.name.label', 'Name')}
                {...register('name')}
                disabled={submitting}
            />
            <FormFieldErrors
                field={'name'}
                errors={errors}
            />
        </FormRow>
    </form>
}

function ListItem({data}: DefinitionItemProps<RenditionClass>) {
    return <ListItemText
        primary={data.name}
    />
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<RenditionClass> {
    return {
        name: '',
    }
}

export default function RenditionClassManager({
                                                  data: workspace,
                                                  minHeight,
                                                  onClose,
                                              }: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: RenditionClass) => {
        if (data.id) {
            return await putRenditionClass(data.id, data);
        } else {
            return await postRenditionClass({
                ...data,
                workspace: `/workspaces/${workspace.id}`
            })
        }
    }

    return <DefinitionManager
        itemComponent={Item}
        listComponent={ListItem}
        load={() => getRenditionClasses(workspace.id)}
        workspaceId={workspace.id}
        minHeight={minHeight}
        onClose={onClose}
        createNewItem={createNewItem}
        newLabel={t('rendition_class.new.label', 'New class')}
        handleSave={handleSave}
        handleDelete={deleteRenditionClass}
    />
}
