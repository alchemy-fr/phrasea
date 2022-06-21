import React from 'react';
import {AttributeClass, Workspace} from "../../../types";
import {
    deleteAttributeClass,
    getWorkspaceAttributeClasses,
    postAttributeClass,
    putAttributeClass
} from "../../../api/attributes";
import {Chip, InputLabel, ListItemText, TextField} from "@mui/material";
import FormRow from "../../Form/FormRow";
import DefinitionManager, {DefinitionItemFormProps, DefinitionItemProps} from "./DefinitionManager";
import {useTranslation} from 'react-i18next';
import {useForm} from "react-hook-form";
import FormFieldErrors from "../../Form/FormFieldErrors";
import CheckboxWidget from "../../Form/CheckboxWidget";
import AclForm from "../../Acl/AclForm";

function Item({
                  data,
                  handleSubmit: onSubmit,
                  formId,
                  submitting,
              }: DefinitionItemFormProps<AttributeClass>) {
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
                label={t('form.attribute_class.name.label', 'Name')}
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
                label={t('form.attribute_class.public.label', 'Public')}
                control={control}
                name={'public'}
                disabled={submitting}
            />
            <FormFieldErrors
                field={'public'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <CheckboxWidget
                label={t('form.attribute_class.editable.label', 'Editable')}
                control={control}
                name={'editable'}
                disabled={submitting}
            />
            <FormFieldErrors
                field={'editable'}
                errors={errors}
            />
        </FormRow>
        {data.id && <FormRow>
            <InputLabel>{t('form.permissions.label', 'Permissions')}</InputLabel>
            <AclForm
                objectId={data.id}
                objectType={'attribute_class'}
                displayedPermissions={['VIEW', 'EDIT', 'ALL']}
            />
        </FormRow>}
    </form>
}

function ListItem({data}: DefinitionItemProps<AttributeClass>) {
    const {t} = useTranslation();

    const publicLabel = data.public ? t('chip.public', 'Public') : t('chip.private', 'Private');
    const editableLabel = data.editable ? t('chip.editable', 'Editable') : t('chip.read_only', 'Read only');

    return <ListItemText
        primary={data.name}
        secondary={<>
            <Chip
                color={data.public ? 'success' : 'error'}
                label={publicLabel}
                size={'small'}
            />
            {' '}
            <Chip
                color={data.editable ? 'success' : 'error'}
                label={editableLabel}
                size={'small'}
            />
        </>}
    />
}

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<AttributeClass> {
    return {
        name: '',
        public: true,
        editable: true,
    }
}

export default function AttributeClassManager({
                                                  data: workspace,
                                                  minHeight,
                                                  onClose,
                                              }: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: AttributeClass) => {
        if (data.id) {
            return await putAttributeClass(data.id, data);
        } else {
            return await postAttributeClass({
                ...data,
                workspace: `/workspaces/${workspace.id}`
            })
        }
    }

    return <DefinitionManager
        itemComponent={Item}
        listComponent={ListItem}
        load={() => getWorkspaceAttributeClasses(workspace.id)}
        workspaceId={workspace.id}
        minHeight={minHeight}
        onClose={onClose}
        createNewItem={createNewItem}
        newLabel={t('attribute_class.new.label', 'New class')}
        handleSave={handleSave}
        handleDelete={deleteAttributeClass}
    />
}
