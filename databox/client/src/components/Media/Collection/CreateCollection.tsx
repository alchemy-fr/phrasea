import FormDialog from '../../Dialog/FormDialog';
import {CollectionForm} from '../../Form/CollectionForm';
import {Collection} from '../../../types';
import {useFormSubmit} from '@alchemy/api';
import {clearWorkspaceCache, postCollection} from '../../../api/collection';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {OnCollectionEdit} from '../../Dialog/Collection/EditCollection';
import React from 'react';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {WorkspaceChip} from '../../Ui/WorkspaceChip.tsx';
import {CollectionChip} from '../../Ui/CollectionChip.tsx';

type Props = {
    parent?: string;
    titlePath?: string[];
    workspaceId?: string;
    workspaceTitle: string;
    onCreate?: OnCollectionEdit;
} & StackedModalProps;

export default function CreateCollection({
    parent,
    titlePath,
    workspaceId,
    workspaceTitle,
    onCreate,
    modalIndex,
    open,
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    const usedFormSubmit = useFormSubmit<Collection>({
        defaultValues: {
            title: '',
            privacy: 0,
        },
        onSubmit: async (data: Collection) => {
            return await postCollection({
                ...data,
                parent,
                workspace: workspaceId
                    ? `/workspaces/${workspaceId}`
                    : undefined,
            });
        },
        onSuccess: coll => {
            clearWorkspaceCache();
            toast.success(
                t(
                    'form.collection_create.success',
                    'Collection created!'
                ) as string
            );
            closeModal();
            onCreate && onCreate(coll);
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useDirtyFormPrompt(forbidNavigation, modalIndex);
    const formId = 'create-collection';

    const title = titlePath ? (
        <>
            {t(
                'form.collection_create.title_with_parent',
                'Create Collection under'
            )}{' '}
            <WorkspaceChip label={workspaceTitle} />
            {titlePath.map((t, i) => (
                <React.Fragment key={i}>
                    {' / '}
                    <CollectionChip label={t} />
                </React.Fragment>
            ))}
        </>
    ) : (
        <>
            {t('form.collection_create.title', 'Create Collection in')}{' '}
            <WorkspaceChip label={workspaceTitle} />
        </>
    );

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={<div>{title}</div>}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            open={open}
        >
            <CollectionForm formId={formId} usedFormSubmit={usedFormSubmit} />
        </FormDialog>
    );
}
