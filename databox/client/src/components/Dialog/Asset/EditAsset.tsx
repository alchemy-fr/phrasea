import React from 'react';
import {Asset} from "../../../types";
import {useTranslation} from "react-i18next";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
import FormTab from "../Tabbed/FormTab";
import {DialogTabProps} from "../Tabbed/TabbedDialog";
import {putAsset} from "../../../api/asset";
import {AssetForm} from "../../Form/AssetForm";

type Props = {
    id: string;
    data: Asset;
} & DialogTabProps;

export default function EditAsset({
                                           data,
                                           onClose,
                                           minHeight,
                                       }: Props) {
    const {t} = useTranslation();

    const {
        submitting,
        submitted,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: Asset) => {
            return await putAsset(data.id, data);
        },
        onSuccess: (item) => {
            toast.success(t('form.asset_edit.success', 'Asset edited!'))
            onClose();
        }
    });

    const formId = 'edit-asset';

    return <FormTab
        onClose={onClose}
        formId={formId}
        loading={submitting}
        errors={errors}
        minHeight={minHeight}
    >
        <AssetForm
            data={data}
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
            submitted={submitted}
            workspaceId={data.workspace.id}
        />
    </FormTab>
}
