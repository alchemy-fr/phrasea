import AppDialog from '../Layout/AppDialog';
import {Button} from '@mui/material';
import {PropsWithChildren, ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import SaveIcon from '@mui/icons-material/Save';
import RemoteErrors from '../Form/RemoteErrors';
import {LoadingButton} from '@mui/lab';
import {StackedModalProps, useModals} from '@alchemy/navigation';

type Props = PropsWithChildren<
    {
        title: ReactNode;
        loading: boolean;
        submittable?: boolean;
        formId?: string;
        onSave?: () => void;
        errors?: ReactNode[];
        submitLabel?: ReactNode;
        submitIcon?: ReactNode;
    } & StackedModalProps
>;

export default function FormDialog({
    title,
    formId,
    onSave,
    errors,
    submitLabel,
    submitIcon,
    loading,
    submittable = true,
    open,
    children,
    modalIndex,
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            loading={loading}
            onClose={closeModal}
            title={title}
            actions={({onClose}) => (
                <>
                    <Button
                        onClick={onClose}
                        color={'warning'}
                        disabled={loading}
                    >
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
                        startIcon={submitIcon || <SaveIcon />}
                        type={formId ? 'submit' : 'button'}
                        form={formId}
                        loading={loading}
                        onClick={onSave}
                        color={'primary'}
                        disabled={!submittable || loading}
                    >
                        {submitLabel || t('dialog.save', 'Save')}
                    </LoadingButton>
                </>
            )}
        >
            {children}
            <RemoteErrors errors={errors} />
        </AppDialog>
    );
}
