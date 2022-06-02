import {Box, TextField, Typography} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import {Workspace} from "../../types";
import FormFieldErrors from "./FormFieldErrors";
import FormRow from "./FormRow";
import {FormProps} from "./types";
import TagFilterRules from "../Media/TagFilterRule/TagFilterRules";
import TagManager from "../Media/Collection/TagManager";
import AclForm from "../Acl/AclForm";

export const WorkspaceForm: FC<FormProps<Workspace>> = function ({
                                                                     formId,
                                                                     data,
                                                                     onSubmit,
                                                                     submitting,
                                                                 }) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        formState: {errors}
    } = useForm<any>({
        defaultValues: data,
    });

    return <>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            <FormRow>
                <TextField
                    autoFocus
                    required={true}
                    label={t('form.workspace.title.label', 'Title')}
                    disabled={submitting}
                    {...register('name', {
                        required: true,
                    })}
                />
                <FormFieldErrors
                    field={'name'}
                    errors={errors}
                />
            </FormRow>
        </form>
        {data && <>
            <hr/>
            <div>
                <h4>Manage tags</h4>
                <TagManager workspaceIri={data['@id']}/>
            </div>
            <hr/>
            <Box sx={{
                mb: 2
            }}>
                <Typography variant={'h2'} >Tag filter rules</Typography>
                <TagFilterRules
                    id={data.id}
                    workspaceId={data.id}
                    type={'workspace'}
                />
            </Box>
            {data.capabilities.canEditPermissions ? <div>
                <hr/>
                <h4>Permissions</h4>
                <AclForm
                    objectId={data.id}
                    objectType={'workspace'}
                />
            </div> : ''}
        </>}
    </>
}