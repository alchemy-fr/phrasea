import React, {useEffect, useState} from 'react';
import {Collection} from "../../../types";
import {getCollection, putCollection} from "../../../api/collection";
import {CollectionForm} from "../../Form/CollectionForm";
import FormDialog from "../../Dialog/FormDialog";
import {useTranslation} from "react-i18next";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import FullPageLoader from "../../Ui/FullPageLoader";
import {useModals} from "@mattjennings/react-modal-stack";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
import AclForm from "../../Acl/AclForm";
// import TagFilterRules from "../TagFilterRule/TagFilterRules";

export type OnCollectionEdit = (coll: Collection) => void;

type Props = {
    id: string;
    onEdit: OnCollectionEdit;
} & StackedModalProps;

export default function EditCollection({
                                           id,
                                           onEdit,
                                       }: Props) {
    const {closeModal} = useModals();
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
        {data.capabilities.canEditPermissions ? <div>
            <hr/>
            <h4>Permissions</h4>
            <AclForm
                objectId={id}
                objectType={'collection'}
            />
        </div> : ''}
    </FormDialog>
}
