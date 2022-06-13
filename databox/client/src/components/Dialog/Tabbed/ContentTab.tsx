import {Button, Container, LinearProgress} from "@mui/material";
import React, {PropsWithChildren} from "react";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import {useTranslation} from 'react-i18next';

type Props<T extends object> = PropsWithChildren<{
    loading?: boolean;
    onClose: () => void;
}>;

export default function ContentTab<T extends object>({
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
            }}
                maxWidth={'lg'}
            >
                {children}
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
                disabled={loading}
            >
                {t('dialog.close', 'Close')}
            </Button>
        </DialogActions>
    </>
}
