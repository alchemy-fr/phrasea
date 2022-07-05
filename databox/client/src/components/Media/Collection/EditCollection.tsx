import React, {useEffect, useState} from 'react';
import {Collection} from "../../../types";
import {getCollection, putCollection} from "../../../api/collection";
import {CollectionForm} from "../../Form/CollectionForm";
import FormDialog from "../../Dialog/FormDialog";
import {useTranslation} from "react-i18next";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import FullPageLoader from "../../Ui/FullPageLoader";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
import AclForm from "../../Acl/AclForm";
import CollectionMoveSection from "./CollectionMoveSection";
import {Typography} from "@mui/material";
import TagRules from "../TagFilterRule/TagRules";
import FormSection from "../../Form/FormSection";
import {useModalHash} from "../../../hooks/useModalHash";

export type OnCollectionEdit = (coll: Collection) => void;

type Props = {
    id: string;
    onEdit: OnCollectionEdit;
} & StackedModalProps;

export default function EditCollection({
                                           id,
                                           onEdit,
                                       }: Props) {
    const {closeModal} = useModalHash();
    const {t} = useTranslation();

    const {submitting, handleSubmit, errors} = useFormSubmit({
        onSubmit: async (data: Collection) => {
            return await putCollection(data.id, data);
        },
        onSuccess: (item) => {
            toast.success(t('form.collection_edit.success', 'Collection edited!'))
            closeModal();
            onEdit(item);
        }
    });
    const [data, setData] = useState<Collection>();

    useEffect(() => {
        getCollection(id).then(c => setData(c));
    }, []);

    if (!data) {
        return <FullPageLoader/>
    }

    const formId = 'edit-collection';

    return <FormDialog
        title={t('form.collection_edit.title', 'Edit collection')}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        <CollectionForm
            data={data}
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
        />
        <FormSection>
            <TagRules
                id={data.id}
                workspaceId={data.workspace.id}
                type={'collection'}
            />
        </FormSection>
        {data.capabilities.canEditPermissions ? <FormSection>
            <Typography variant={'h2'}>
                {t('collection_edit.permissions.title', 'Permissions')}
            </Typography>
            <AclForm
                objectId={id}
                objectType={'collection'}
            />
        </FormSection> : ''}
        <FormSection>
            <CollectionMoveSection
                collection={data}
                onMoved={() => {
                    closeModal();
                    onEdit(data);
                }}
            />
        </FormSection>
    </FormDialog>
}
