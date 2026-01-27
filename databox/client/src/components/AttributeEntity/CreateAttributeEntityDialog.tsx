import {AttributeEntity} from '../../types.ts';
import {useTranslation} from 'react-i18next';
import {AppDialog} from '@alchemy/phrasea-ui';
import {StackedModalProps, useFormPrompt, useModals} from '@alchemy/navigation';
import {Button} from '@mui/material';
import {getNonEmptyTranslations} from '@alchemy/react-form';
import {postAttributeEntity} from '../../api/attributeEntity.ts';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import React from 'react';
import AttributeEntityFields from './AttributeEntityFields.tsx';
import {useWorkspace} from '../../hooks/useWorkspace.ts';

type Props = {
    value: string;
    list: string;
    workspaceId: string;
    onCreate: (entity: AttributeEntity) => void;
} & StackedModalProps;

export default function CreateAttributeEntityDialog({
    open,
    modalIndex,
    value,
    list,
    workspaceId,
    onCreate,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const formId = 'attr-entity';
    const workspace = useWorkspace(workspaceId);

    const usedFormSubmit = useFormSubmit<AttributeEntity>({
        defaultValues: {
            value,
        },
        onSubmit: async data => {
            const d = {
                ...data,
                translations: getNonEmptyTranslations(data.translations ?? {}),
            };

            return await postAttributeEntity(list, d);
        },
        onSuccess: data => {
            onCreate(data);

            toast.success(
                t('attribute_entity.form.created', 'Item created!') as string
            );
            closeModal();
        },
    });
    const {submitting, forbidNavigation, handleSubmit} = usedFormSubmit;

    useFormPrompt(t, forbidNavigation, modalIndex);

    return (
        <AppDialog
            onClose={closeModal}
            open={open}
            modalIndex={modalIndex}
            title={t('attribute_entity.dialog.create.title', 'New Item')}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        loading={submitting}
                        disabled={submitting}
                        variant={'contained'}
                        form={formId}
                        type={'submit'}
                        color={'primary'}
                    >
                        {t('common.save', 'Save')}
                    </Button>
                </>
            )}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <AttributeEntityFields
                    usedFormSubmit={usedFormSubmit}
                    workspace={workspace}
                />
            </form>
        </AppDialog>
    );
}
