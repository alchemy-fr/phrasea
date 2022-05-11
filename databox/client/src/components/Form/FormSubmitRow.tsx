import {Box, CircularProgress, Fab, Tooltip} from "@mui/material";
import CheckIcon from "@mui/icons-material/Check";
import SaveIcon from "@mui/icons-material/Save";
import {green, red} from "@mui/material/colors";
import CloseIcon from "@mui/icons-material/Close";
import ErrorIcon from "@mui/icons-material/Error";
import {ReactNode, useEffect, useState} from "react";
import {useTranslation} from "react-i18next";

type Props = {
    saving: boolean;
    success: boolean;
    onCancel?: () => void;
    error?: ReactNode;
    remoteError?: string | undefined;
}

export default function FormSubmitRow({
                                          saving,
                                          success,
                                          onCancel,
                                          error,
                                          remoteError,
                                      }: Props) {
    const hasError = Boolean(error) || Boolean(remoteError);
    const color = hasError ? red : green;

    const [errored, setErrored] = useState(hasError);

    const {t} = useTranslation('admin');

    useEffect(() => {
        if (hasError) {
            setErrored(true);
            const t = setTimeout(() => setErrored(false), 1000);

            return () => {
                clearTimeout(t);
            }
        }
    }, [hasError]);

    const buttonSx = errored || success ? {
        bgcolor: color[500],
        '&:hover': {
            bgcolor: color[700],
        },
    } : {};

    return <div className="form-group">
        <Tooltip
            arrow
            placement={'top'}
            title={t('form.save', 'Save') as string}
        >
            <Box sx={{
                m: 1,
                position: 'relative',
                display: 'inline-block'
            }}>

                <Fab
                    aria-label="save"
                    color="primary"
                    sx={buttonSx}
                    type={'submit'}
                >
                    {errored && <ErrorIcon/>}
                    {!errored && (success ? <CheckIcon/> : <SaveIcon/>)}
                </Fab>
                {saving && (
                    <CircularProgress
                        size={68}
                        sx={{
                            color: green[500],
                            position: 'absolute',
                            top: -6,
                            left: -6,
                            zIndex: 1,
                        }}
                    />
                )}
            </Box>
        </Tooltip>
        {onCancel && <Tooltip
            color={'success'}
            sx={{
                fontSize: 'large',
            }}
            arrow
            placement={'top'}
            title={t('form.cancel_and_close', 'Cancel and close') as string}
        >
            <Fab
                aria-label="save"
                color="default"
                type={'button'}
                onClick={onCancel}
                disabled={saving}
            >
                <CloseIcon/>
            </Fab>
        </Tooltip>}
        {(error || remoteError) && <div className="form-errors">
            {error || ''}
            {remoteError || ''}
        </div>}
    </div>
}
