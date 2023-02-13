import React from 'react';
import FormDialog from "../../Dialog/FormDialog";
import {CollectionForm} from "../../Form/CollectionForm";
import {Collection} from "../../../types";
import useFormSubmit from "../../../hooks/useFormSubmit";
import {clearWorkspaceCache, postCollection} from "../../../api/collection";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import {CollectionChip, WorkspaceChip} from "../../Ui/Chips";
import {StackedModalProps, useModals} from "../../../hooks/useModalStack";
import {OnCollectionEdit} from "../../Dialog/Collection/EditCollection";

type Props = {
    parent?: string;
    titlePath?: string[];
    workspaceId?: string;
    workspaceTitle: string;
    onCreate: OnCollectionEdit;
} & StackedModalProps;

export default function CreateCollection({
    parent,
    titlePath,
    workspaceId,
    workspaceTitle,
    onCreate,
    open,
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();
    const {
        submitting,
        submitted,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: Collection) => {
            return await postCollection({
                ...data,
                parent,
                workspace: workspaceId ? `/workspaces/${workspaceId}` : undefined,
            });
        },
        onSuccess: (coll) => {
            clearWorkspaceCache();
            toast.success(t('form.collection_create.success', 'Collection created!'));
            closeModal();
            onCreate(coll);
        }
    });

    const formId = 'create-collection';

    const title = titlePath ? <>
            {t('form.collection_create.title_with_parent', 'Create collection under')}
            {' '}
            <WorkspaceChip label={workspaceTitle}/>
            {titlePath.map((t, i) => <React.Fragment key={i}>
                {' / '}
                <CollectionChip label={t}/>
            </React.Fragment>)}
        </>
        : <>
            {t('form.collection_create.title', 'Create collection in')}
            {' '}
            <WorkspaceChip label={workspaceTitle}/>
        </>;

    return <FormDialog
        title={title}
        formId={formId}
        loading={submitting}
        errors={errors}
        open={open}
    >
        <CollectionForm
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
            submitted={submitted}
        />
    </FormDialog>
}
