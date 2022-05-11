import * as React from 'react';
import {styled} from '@mui/material/styles';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import IconButton from '@mui/material/IconButton';
import CloseIcon from '@mui/icons-material/Close';
import {PropsWithChildren, ReactNode} from "react";
import {LinearProgress} from "@mui/material";

const BootstrapDialog = styled(Dialog)(({theme}) => ({
    '& .MuiDialogContent-root': {
        padding: theme.spacing(2),
    },
    '& .MuiDialogActions-root': {
        padding: theme.spacing(1),
    },
}));

export interface DialogTitleProps {
    id: string;
    children?: React.ReactNode;
    onClose: () => void;
}

const BootstrapDialogTitle = (props: DialogTitleProps) => {
    const {children, onClose, ...other} = props;

    return (
        <DialogTitle sx={{m: 0, p: 2}} {...other}>
            {children}
            {onClose ? (
                <IconButton
                    aria-label="close"
                    onClick={onClose}
                    sx={{
                        position: 'absolute',
                        right: 8,
                        top: 8,
                        color: (theme) => theme.palette.grey[500],
                    }}
                >
                    <CloseIcon/>
                </IconButton>
            ) : null}
        </DialogTitle>
    );
};

type ActionArgs = {
    onClose: () => void,
    loading?: boolean;
};

type Props = PropsWithChildren<{
    title?: ReactNode;
    actions?: (args: ActionArgs) => React.ReactNode;
    onClose: () => void;
    loading?: boolean;
}>;

const progressHeight = 3;

export default function AppDialog({
                                      title,
                                      children,
                                      actions,
                                      loading,
                                      onClose,
                                  }: Props) {
    const [open, setOpen] = React.useState(true);

    const handleClose = () => {
        setOpen(false);
        onClose();
    };

    return <BootstrapDialog
        onClose={handleClose}
        aria-labelledby="customized-dialog-title"
        open={open}
        fullWidth={true}
        maxWidth={'md'}
    >
        {title && <BootstrapDialogTitle id="dialog-title" onClose={handleClose}>
            {title}
        </BootstrapDialogTitle>}
        <DialogContent dividers>
            {children}

        </DialogContent>
        {loading && <LinearProgress
            style={{
                height: progressHeight,
                marginBottom: -progressHeight
            }}
        />}
        {actions && <DialogActions>
            {actions({
                onClose: handleClose,
                loading,
            })}
        </DialogActions>}
    </BootstrapDialog>
}
