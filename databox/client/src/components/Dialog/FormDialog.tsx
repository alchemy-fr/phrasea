import AppDialog from "../Layout/AppDialog";
import {Alert, Button} from "@mui/material";
import React, {PropsWithChildren, ReactNode} from "react";
import {useModals} from "@mattjennings/react-modal-stack";
import {useTranslation} from "react-i18next";
import SaveIcon from '@mui/icons-material/Save';


type Props<T extends object> = PropsWithChildren<{
    title: ReactNode;
    loading: boolean;
    formId?: string;
    onSave?: () => void;
    errors?: ReactNode[];
    submitLabel?: ReactNode;
    submitIcon?: ReactNode;
}>;

export default function FormDialog<T extends object>({
                                                         title,
                                                         formId,
                                                         onSave,
                                                         errors,
                                                         submitLabel,
                                                         submitIcon,
                                                         loading,
                                                         children,
                                                     }: Props<T>) {
    const {closeModal} = useModals();
    const {t} = useTranslation();

    return <AppDialog
        loading={loading}
        onClose={closeModal}
        title={title}
        actions={({onClose}) => <>
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
                onClick={onSave}
                color={'primary'}
                disabled={loading}
            >
                {submitLabel || t('dialog.save', 'Save')}
            </Button>
        </>}
    >
        {children}
        {errors && <div>{errors.map((e, i) => <Alert key={i} severity="error">{e}</Alert>)}</div>}
    </AppDialog>
}
