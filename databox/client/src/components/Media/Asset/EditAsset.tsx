import React, {useEffect, useState} from 'react';
import {Asset} from "../../../types";
import FormDialog from "../../Dialog/FormDialog";
import {useTranslation} from "react-i18next";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import FullPageLoader from "../../Ui/FullPageLoader";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
import {AssetForm} from "../../Form/AssetForm";
import {getAsset, putAsset} from "../../../api/asset";
import AclForm from "../../Acl/AclForm";
import {useModalHash} from "../../../hooks/useModalHash";

type Props = {
    id: string;
    onEdit: () => void;
} & StackedModalProps;

export default function EditAsset({
                                      id,
                                      onEdit,
                                  }: Props) {
    const {closeModal} = useModalHash();
    const {t} = useTranslation();

    const {submitting, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: Asset) => {
            return await putAsset(data.id, data);
        },
        onSuccess: () => {
            toast.success(t('form.asset_edit.success', 'Asset edited!'))
            closeModal();
            onEdit();
        }
    });
    const [data, setData] = useState<Asset>();

    useEffect(() => {
        getAsset(id).then(c => setData(c));
    }, []);

    if (!data) {
        return <FullPageLoader/>
    }

    const formId = 'edit-asset';

    return <FormDialog
        title={t('form.asset_edit.title', 'Edit asset')}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        <AssetForm
            data={data}
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
            workspaceId={data!.workspace.id}
        />
        {data.capabilities.canEditPermissions ? <div>
            <hr/>
            <h4>Permissions</h4>
            <AclForm
                objectId={data.id}
                objectType={'asset'}
            />
        </div> : ''}
    </FormDialog>
}
