import React from 'react';
import FormDialog from "../../Dialog/FormDialog";
import {StackedModalProps} from "@mattjennings/react-modal-stack/src/ModalStack";
import {Asset} from "../../../types";
import useFormSubmit from "../../../hooks/useFormSubmit";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import {CollectionChip, WorkspaceChip} from "../../Ui/Chips";
import {AssetForm} from "../../Form/AssetForm";
import {postAsset} from "../../../api/asset";
import {useModalHash} from "../../../hooks/useModalHash";

type Props = {
    titlePath?: string[];
    workspaceId: string;
    collectionId?: string;
    workspaceTitle: string;
} & StackedModalProps;

export default function CreateAsset({
                                        titlePath,
                                        workspaceId,
                                        collectionId,
                                        workspaceTitle,
                                    }: Props) {
    const {closeModal} = useModalHash();
    const {t} = useTranslation();
    const {
        submitting,
        handleSubmit,
        errors,
    } = useFormSubmit({
        onSubmit: async (data: Asset) => {
            return await postAsset({
                ...data,
                collection: collectionId ? `/collections/${collectionId}` : undefined,
                workspace: workspaceId ? `/workspaces/${workspaceId}` : undefined,
            });
        },
        onSuccess: (coll) => {
            toast.success(t('form.asset_create.success', 'Asset created!'))
            closeModal();
        }
    });

    const formId = 'create-asset';

    const title = titlePath ? <>
            {t('form.asset_create.title_with_parent', 'Create asset under')}
            {' '}
            <WorkspaceChip label={workspaceTitle}/>
            {titlePath.map((t, i) => <React.Fragment key={i}>
                {' / '}
                <CollectionChip label={t}/>
            </React.Fragment>)}
        </>
        : <>
            {t('form.asset_create.title', 'Create asset in')}
            {' '}
            <WorkspaceChip label={workspaceTitle}/>
        </>;

    return <FormDialog
        title={title}
        formId={formId}
        loading={submitting}
        errors={errors}
    >
        <AssetForm
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
            workspaceId={workspaceId}
        />
    </FormDialog>
}
