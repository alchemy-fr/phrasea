import React from 'react';
import {Tag, Workspace} from "../../../types";
import {ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemFormProps, DefinitionItemProps} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import {useForm} from "react-hook-form";
import FormFieldErrors from "../../Form/FormFieldErrors";
import {deleteTag, getTags, postTag, putTag} from "../../../api/tag";
import {useDirtyFormPrompt} from "../Tabbed/FormTab";

function Item({
                  data,
                  handleSubmit: onSubmit,
                  formId,
                  submitting,
              }: DefinitionItemFormProps<Tag>) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        formState: {errors, isDirty}
    } = useForm<any>({
        defaultValues: data,
    });
    useDirtyFormPrompt(isDirty);

    return <form
        id={formId}
        onSubmit={handleSubmit(onSubmit(setError))}>
        <FormRow>
            <TextField
                label={t('form.tag.name.label', 'Name')}
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

function ListItem({data}: DefinitionItemProps<Tag>) {
    return <ListItemText
        primary={data.name}
    />
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<Tag> {
    return {
        name: '',
    }
}

export default function TagManager({
                                       data: workspace,
                                       minHeight,
                                       onClose,
                                   }: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: Tag) => {
        if (data.id) {
            return await putTag(data.id, data);
        } else {
            return await postTag({
                ...data,
                workspace: `/workspaces/${workspace.id}`
            })
        }
    }

    return <DefinitionManager
        itemComponent={Item}
        listComponent={ListItem}
        load={() => getTags({
            workspace: workspace['@id']
        }).then(r => r.result)}
        workspaceId={workspace.id}
        minHeight={minHeight}
        onClose={onClose}
        createNewItem={createNewItem}
        newLabel={t('tags.new.label', 'New tag')}
        handleSave={handleSave}
        handleDelete={deleteTag}
    />
}
