import React from 'react';
import {RenditionClass, Workspace} from "../../../types";
import {InputLabel, ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemFormProps, DefinitionItemProps} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import {useForm} from "react-hook-form";
import FormFieldErrors from "../../Form/FormFieldErrors";
import {deleteRenditionClass, getRenditionClasses, postRenditionClass, putRenditionClass} from "../../../api/rendition";
import CheckboxWidget from "../../Form/CheckboxWidget";
import RenditionClassPermissions from "./RenditionClassPermissions";
import {useDirtyFormPrompt} from "../Tabbed/FormTab";

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
        watch,
        control,
        formState: {errors, isDirty}
    } = useForm<any>({
        defaultValues: data,
    });
    useDirtyFormPrompt(isDirty);

    const isPublic = watch('public');

    return <>
        <form
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
            <FormRow>
                <CheckboxWidget
                    label={t('form.rendition_class.public.label', 'Public')}
                    control={control}
                    name={'public'}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={'public'}
                    errors={errors}
                />
            </FormRow>
        </form>
        {data.id && !isPublic && <FormRow>
            <InputLabel>{t('form.permissions.label', 'Permissions')}</InputLabel>
            <RenditionClassPermissions
                classId={data.id}
                workspaceId={(data.workspace as Workspace).id}
            />
        </FormRow>}
    </>
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
        public: true,
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
            const postData = {...data} as Partial<RenditionClass>;
            delete postData.workspace;

            return await putRenditionClass(data.id, postData);
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
        load={() => getRenditionClasses(workspace.id).then(r => r.result)}
        workspaceId={workspace.id}
        minHeight={minHeight}
        onClose={onClose}
        createNewItem={createNewItem}
        newLabel={t('rendition_class.new.label', 'New class')}
        handleSave={handleSave}
        handleDelete={deleteRenditionClass}
    />
}
