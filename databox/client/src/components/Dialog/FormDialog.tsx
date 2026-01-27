import {AppDialog} from '@alchemy/phrasea-ui';
import {Breakpoint, Button} from '@mui/material';
import {PropsWithChildren, ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import SaveIcon from '@mui/icons-material/Save';
import {RemoteErrors} from '@alchemy/react-form';
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
        maxWidth?: Breakpoint | false;
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
    maxWidth,
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
            maxWidth={maxWidth}
            actions={({onClose}) => (
                <>
                    <Button
                        onClick={onClose}
                        color={'warning'}
                        disabled={loading}
                    >
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                    <Button
                        startIcon={submitIcon || <SaveIcon />}
                        type={formId ? 'submit' : 'button'}
                        form={formId}
                        loading={loading}
                        onClick={onSave}
                        color={'primary'}
                        disabled={!submittable || loading}
                    >
                        {submitLabel || t('dialog.save', 'Save')}
                    </Button>
                </>
            )}
        >
            {children}
            <RemoteErrors errors={errors} />
        </AppDialog>
    );
}
