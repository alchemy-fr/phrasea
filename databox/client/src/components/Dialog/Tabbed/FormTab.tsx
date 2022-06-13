import {Button, Container, LinearProgress} from "@mui/material";
import React, {PropsWithChildren, ReactNode} from "react";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import {LoadingButton} from "@mui/lab";
import SaveIcon from "@mui/icons-material/Save";
import RemoteErrors from "../../Form/RemoteErrors";
import {useTranslation} from 'react-i18next';

type Props<T extends object> = PropsWithChildren<{
    loading: boolean;
    formId?: string;
    onSave?: () => void;
    errors?: ReactNode[];
    submitLabel?: ReactNode;
    submitIcon?: ReactNode;
    onClose: () => void;
}>;

export default function FormTab<T extends object>({
                                                      formId,
                                                      onSave,
                                                      errors,
                                                      submitLabel,
                                                      submitIcon,
                                                      loading,
                                                      children,
                                                      onClose,
                                                  }: Props<T>) {
    const {t} = useTranslation();
    const progressHeight = 3;

    return <>
        <DialogContent dividers>
            <Container sx={{
                pt: 2
            }}>
                {children}
                <RemoteErrors errors={errors}/>
            </Container>
        </DialogContent>
        {loading && <LinearProgress
            style={{
                height: progressHeight,
                marginBottom: -progressHeight
            }}
        />}
        <DialogActions>
            <Button
                onClick={onClose}
                color={'warning'}
                disabled={loading}
            >
                {t('dialog.cancel', 'Cancel')}
            </Button>
            <LoadingButton
                startIcon={submitIcon || <SaveIcon/>}
                type={formId ? 'submit' : 'button'}
                form={formId}
                loading={loading}
                onClick={onSave}
                color={'primary'}
                disabled={loading}
            >
                {submitLabel || t('dialog.save', 'Save')}
            </LoadingButton>
        </DialogActions>
    </>
}
