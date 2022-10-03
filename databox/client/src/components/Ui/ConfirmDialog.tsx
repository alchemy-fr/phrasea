import React, {PropsWithChildren, ReactNode, useState} from 'react';
import AppDialog from "../Layout/AppDialog";
import {Button, TextField} from "@mui/material";
import {useTranslation} from "react-i18next";
import CheckIcon from '@mui/icons-material/Check';
import {LoadingButton} from "@mui/lab";
import {AxiosError} from "axios";
import RemoteErrors from "../Form/RemoteErrors";
import {StackedModalProps, useModals} from "../../hooks/useModalStack";

type Props = PropsWithChildren<{
    onCancel?: () => void;
    onConfirm: () => Promise<void>;
    title: ReactNode;
    confirmLabel?: ReactNode;
    disabled?: boolean;
    textToType?: string | undefined;
} & StackedModalProps>;

export default function ConfirmDialog({
                                          onCancel,
                                          onConfirm,
                                          title,
                                          confirmLabel,
                                          disabled,
                                          open,
                                          textToType,
                                          children,
                                      }: Props) {
    const {closeModal} = useModals();
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const {t} = useTranslation();
    const [confirmValue, setConfirmValue] = useState('');

    const onClose = () => {
        closeModal();
        onCancel && onCancel();
    }

    const confirm = async () => {
        setLoading(true);
        setErrors([]);
        try {
            await onConfirm();
            closeModal();
        } catch (e: any) {
            if (e.isAxiosError) {
                const err = e as AxiosError<any>;
                if (err.response && [400, 500, 404].includes(err.response.status)) {
                    setErrors(p => p.concat(err.response!.data['hydra:description'] as string));
                }
            }
            setLoading(false);
        }
    }

    return <AppDialog
        maxWidth={'sm'}
        onClose={onClose}
        loading={loading}
        title={title}
        open={open}
        actions={({onClose}) => <>
            <Button
                onClick={onClose}
                color={'warning'}
                disabled={loading}
            >
                {t('dialog.cancel', 'Cancel')}
            </Button>
            <LoadingButton
                loading={loading}
                startIcon={<CheckIcon/>}
                onClick={confirm}
                color={'success'}
                disabled={disabled || (textToType ? textToType !== confirmValue : false)}
            >
                {confirmLabel || t('dialog.confirm', 'Confirm')}
            </LoadingButton>
        </>}
    >
        {textToType && <div>
            {t('dialog.confirm_text_type.intro', 'Please type "{{ text }}" to confirm:', {
                text: textToType,
            })}
            <div>
                <TextField
                    disabled={loading}
                    value={confirmValue}
                    onChange={e => setConfirmValue(e.target.value)}
                    placeholder={t('dialog.confirm_text_type.placeholder', 'Type "{{ text }}"', {
                        text: textToType,
                    })}
                />
            </div>
        </div>}
        {children}
        <RemoteErrors errors={errors}/>
    </AppDialog>
}
