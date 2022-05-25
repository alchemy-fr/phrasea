import React, {PropsWithChildren, ReactNode, useState} from 'react';
import AppDialog from "../Layout/AppDialog";
import {Alert, Box, Button} from "@mui/material";
import {useTranslation} from "react-i18next";
import {useModals} from "@mattjennings/react-modal-stack";
import CheckIcon from '@mui/icons-material/Check';
import {LoadingButton} from "@mui/lab";
import {AxiosError} from "axios";
import {mapApiErrors} from "../../lib/form";

type Props = PropsWithChildren<{
    onCancel?: () => void;
    onConfirm: () => Promise<void>;
    title: ReactNode;
    confirmLabel?: ReactNode;
    disabled?: boolean;
}>;

export default function ConfirmDialog({
                                          onCancel,
                                          onConfirm,
                                          title,
                                          confirmLabel,
                                          disabled,
                                          children,
                                      }: Props) {
    const {closeModal} = useModals();
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);
    const {t} = useTranslation();

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
                disabled={disabled}
            >
                {confirmLabel || t('dialog.confirm', 'Confirm')}
            </LoadingButton>
        </>}
    >
        {children}
        {errors && <Box
            sx={{mt: 2}}
        >{errors.map((e, i) => <Alert key={i} severity="error">{e}</Alert>)}</Box>}
    </AppDialog>
}
