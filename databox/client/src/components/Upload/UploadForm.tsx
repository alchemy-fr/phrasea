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
import {buildAttributeIndex, useAttributeEditor} from "../Media/Asset/Attribute/useAttributeEditor";
import {Collection} from "../Media/Collection/CollectionsTreeView";
import SaveAsTemplateForm from "./SaveAsTemplateForm";
import {useAssetDataTemplateOptions} from "../Media/Asset/Attribute/useAssetDataTemplateOptions";
import {getAssetDataTemplate} from "../../api/templates";
import AssetDataTemplateSelect from "../Form/AssetDataTemplateSelect";
import {OnChangeValue} from "react-select/dist/declarations/src/types";
import {SelectOption} from "../Form/RSelect";
import {Attribute, Tag} from "../../types";
import {AttributeIndex} from "../Media/Asset/Attribute/AttributesEditor";

export type UploadData = {
    destination: Collection;
    privacy: Privacy;
    tags: string[];
};

export const UploadForm: FC<{
    workspaceId?: string | undefined;
    noDestination?: boolean | undefined;
    usedAttributeEditor: ReturnType<typeof useAttributeEditor>;
    usedAssetDataTemplateOptions: ReturnType<typeof useAssetDataTemplateOptions>;
    onChangeWorkspace: (wsId: string | undefined) => void;
} & FormProps<UploadData>> = function ({
    formId,
    onSubmit,
    submitting,
    submitted,
    workspaceId,
    noDestination,
    usedAttributeEditor,
    usedAssetDataTemplateOptions,
    onChangeWorkspace,
}) {
    const {t} = useTranslation();

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

    const onTemplateSelect = async (value: OnChangeValue<SelectOption, false>) => {
        if (!value) {
            return;
        }
        const t = await getAssetDataTemplate(value.value);

        if (t.tags) {
            setValue('tags', (t.tags as Tag[])!.map((t) => t['@id']) as any);
        }
        if (undefined !== t.privacy && null !== t.privacy) {
            setValue('privacy', t.privacy);
        }

        if (t.attributes) {
            const definitionIndex = usedAttributeEditor.definitionIndex;
            if (definitionIndex) {
                const attrIndex: AttributeIndex = buildAttributeIndex(definitionIndex, t.attributes as Attribute[]);
                const setAttr = usedAttributeEditor.onChangeHandler;

                Object.keys(attrIndex).map(defId => {
                    Object.keys(attrIndex[defId]).map(locale => {
                        setAttr(defId, locale, attrIndex[defId][locale]);
                    });
                });
            }
        }
    }

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
                    onChange={(s, wsId) => onChangeWorkspace(wsId)}
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
                        {t('form.asset.templates.label', 'Fill with template')}
                    </InputLabel>
                    <AssetDataTemplateSelect
                        workspaceId={workspaceId}
                        onChange={onTemplateSelect}
                    />
                </FormGroup>
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
            usedAttributeEditor={usedAttributeEditor}
        />}

        <SaveAsTemplateForm
            usedAssetDataTemplateOptions={usedAssetDataTemplateOptions}
        />
    </>
}
