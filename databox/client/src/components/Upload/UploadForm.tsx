import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import FormRow from "../Form/FormRow";
import FormFieldErrors from "../Form/FormFieldErrors";
import {FormProps} from "../Form/types";
import CollectionTreeWidget from "../Form/CollectionTreeWidget";
import PrivacyField from "../Ui/PrivacyField";
import {Privacy} from "../../api/privacy";
import {FormGroup, InputLabel} from "@mui/material";
import TagSelect from "../Form/TagSelect";
import {useNavigationPrompt} from "../../hooks/useNavigationPrompt";
import UploadAttributes from "./UploadAttributes";
import {OnAttributesChange} from "../Media/Asset/Attribute/useAttributeEditor";
import {Collection} from "../Media/Collection/CollectionsTreeView";

export type UploadData = {
    destination: Collection;
    privacy: Privacy;
    tags: string[];
};

export const UploadForm: FC<{
    workspaceId?: string | undefined;
    noDestination?: boolean | undefined;
    onAttributesChange: OnAttributesChange;
} & FormProps<UploadData>> = function ({
    formId,
    onSubmit,
    submitting,
    submitted,
    workspaceId: initWsId,
    noDestination,
    onAttributesChange,
}) {
    const {t} = useTranslation();
    const [workspaceId, setWorkspaceId] = React.useState<string | undefined>(initWsId);

    const {
        handleSubmit,
        control,
        setError,
        setValue,
        formState: {errors, isDirty}
    } = useForm<UploadData>({
        defaultValues: {
            destination: '',
            privacy: Privacy.Secret,
            tags: [],
        },
    });
    useNavigationPrompt('Are you sure you want to dismiss upload?', !submitting && !submitted && isDirty);

    React.useEffect(() => {
        setValue('tags', []);
        onAttributesChange(undefined);
    }, [workspaceId, setValue]);

    return <>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            {!noDestination && <FormRow>
                <CollectionTreeWidget
                    control={control}
                    rules={{
                        required: true,
                    }}
                    name={'destination'}
                    onChange={(s, wsId) => setWorkspaceId(wsId)}
                    label={t('form.upload.destination.label', 'Destination')}
                    required={true}
                    allowNew={true}
                    disabled={submitting}
                />
                <FormFieldErrors
                    field={'destination'}
                    errors={errors}
                />
            </FormRow>}
            {workspaceId && <FormRow>
                <FormGroup>
                    <InputLabel>
                        {t('form.asset.tags.label', 'Tags')}
                    </InputLabel>
                    <TagSelect
                        workspaceId={workspaceId}
                        control={control}
                        name={'tags'}
                    />
                    <FormFieldErrors
                        field={'tags'}
                        errors={errors}
                    />
                </FormGroup>
            </FormRow>}
            <FormRow>
                <PrivacyField
                    control={control}
                    name={'privacy'}
                />
            </FormRow>
        </form>

        {workspaceId && <UploadAttributes
            workspaceId={workspaceId}
            onAttributesChange={onAttributesChange}
        />}
    </>
}
