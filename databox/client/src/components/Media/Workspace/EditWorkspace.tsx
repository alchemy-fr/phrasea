import React, {useEffect, useState} from 'react';
import {Workspace} from "../../../types";
import {putWorkspace} from "../../../api/collection";
import EditDialog from "../../Dialog/EditDialog";
import {useTranslation} from "react-i18next";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import FullPageLoader from "../../Ui/FullPageLoader";
import {useModals} from "@mattjennings/react-modal-stack";
import {toast} from "react-toastify";
import useFormSubmit from "../../../hooks/useFormSubmit";
import {WorkspaceForm} from "../../Form/WorkspaceForm";
import {getWorkspace} from "../../../api/workspace";
// import TagFilterRules from "../TagFilterRule/TagFilterRules";

export type OnWorkspaceEdit = (item: Workspace) => void;

type Props = {
    id: string;
    onEdit: OnWorkspaceEdit;
} & StackedModalProps;

export default function EditWorkspace({
                                          id,
                                          onEdit,
                                      }: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const {
        submitting,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: Workspace) => {
            return await putWorkspace(data.id, data);
        },
        onSuccess: (item) => {
            toast.success(t('form.workspace_edit.success', 'Workspace edited!'))
            closeModal();
            onEdit(item);
        }
    });
    const [data, setData] = useState<Workspace>();

    useEffect(() => {
        getWorkspace(id).then(c => setData(c));
    }, []);

    if (!data) {
        return <FullPageLoader/>
    }

    const formId = 'edit-ws';

    return <EditDialog
        title={t('form.workspace_edit.title', 'Edit workspace')}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        <WorkspaceForm
            data={data}
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
        />
    </EditDialog>
}
