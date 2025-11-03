import React from 'react';
import {useTranslation} from 'react-i18next';
import {AssetAttachment} from '../../../../types';
import {StackedModalProps, useFormPrompt, useModals} from '@alchemy/navigation';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import {putAttachment} from '../../../../api/attachment.ts';
import {Button, TextField} from '@mui/material';
import {LoadingButton} from '@mui/lab';
import {AppDialog} from '@alchemy/phrasea-ui';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';

type Props = {
    attachment: AssetAttachment;
    onEdit: (attachment: AssetAttachment) => void;
} & StackedModalProps;

export default function RenameAttachmentDialog({
    attachment,
    onEdit,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {
        register,
        formState: {errors},
        submitting,
        forbidNavigation,
        handleSubmit,
    } = useFormSubmit<AssetAttachment>({
        defaultValues: attachment,
        onSubmit: async data => {
            return await putAttachment(data.id, {
                name: data.name,
            });
        },
        onSuccess: data => {
            onEdit(data);

            toast.success(
                t(
                    'rename_attachment.dialog.success',
                    'Attachment has been renamed.'
                )
            );
            closeModal();
        },
    });
    useFormPrompt(t, forbidNavigation, modalIndex);

    const formId = 'rename-attachment-form';

    return (
        <AppDialog
            onClose={closeModal}
            open={open}
            modalIndex={modalIndex}
            title={t('rename_attachment.dialog.title', 'Rename Attachment')}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
                        loading={submitting}
                        disabled={submitting}
                        variant={'contained'}
                        form={formId}
                        type={'submit'}
                        color={'primary'}
                    >
                        {t('common.save', 'Save')}
                    </LoadingButton>
                </>
            )}
        >
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        label={t(
                            'rename_attachment.form.label',
                            'Attachment Name'
                        )}
                        fullWidth
                        {...register('name')}
                        error={!!errors['name']}
                    />
                    <FormFieldErrors field="name" errors={errors} />
                </FormRow>
            </form>
        </AppDialog>
    );
}
