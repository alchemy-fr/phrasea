import * as React from 'react';
import {PropsWithChildren, ReactNode} from 'react';
import {styled} from '@mui/material/styles';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import IconButton from '@mui/material/IconButton';
import CloseIcon from '@mui/icons-material/Close';
import {LinearProgress} from "@mui/material";
import {Breakpoint} from "@mui/system";

export const BootstrapDialog = styled(Dialog)(({theme}) => ({
    '& .MuiDialogContent-root': {
        padding: theme.spacing(2),
    },
    '& .MuiDialogActions-root': {
        padding: theme.spacing(1),
    },
}));

export interface DialogTitleProps {
    children?: React.ReactNode;
    onClose: () => void;
}

export const AppDialogTitle = (props: DialogTitleProps) => {
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
    maxWidth?: Breakpoint | false;
}>;

export default function AppDialog({
                                      title,
                                      children,
                                      actions,
                                      loading,
                                      onClose,
                                      maxWidth = 'md',
                                  }: Props) {
    const progressHeight = 3;
    const [open, setOpen] = React.useState(true);

    const handleClose = () => {
        setOpen(false);
        onClose();
    };

    return <BootstrapDialog
        onClose={handleClose}
        open={open}
        fullWidth={true}
        maxWidth={maxWidth}
    >
        {title && <AppDialogTitle onClose={handleClose}>
            {title}
        </AppDialogTitle>}
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
