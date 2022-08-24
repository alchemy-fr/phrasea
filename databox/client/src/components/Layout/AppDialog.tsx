import * as React from 'react';
import {PropsWithChildren, ReactNode} from 'react';
import {styled} from '@mui/material/styles';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import IconButton from '@mui/material/IconButton';
import CloseIcon from '@mui/icons-material/Close';
import {LinearProgress, Slide} from "@mui/material";
import {Breakpoint} from "@mui/system";
import {StackedModalProps} from "../../hooks/useModalStack";
import {TransitionProps} from "@mui/material/transitions";

export const BootstrapDialog = styled(Dialog)(({theme}) => ({
    '& .MuiDialogContent-root': {
        padding: theme.spacing(2),
    },
    '& .MuiDialogActions-root': {
        padding: theme.spacing(1),
    },
}));


const Transition = React.forwardRef(function Transition(
    props: TransitionProps & {
        children: React.ReactElement<any, any>;
    },
    ref: React.Ref<unknown>,
) {
    return <Slide direction="up" ref={ref} {...props} />;
});

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
} & StackedModalProps>;

export default function AppDialog({
                                      title,
                                      children,
                                      actions,
                                      loading,
                                      onClose,
                                      open = true,
                                      maxWidth = 'md',
                                  }: Props) {
    const progressHeight = 3;

    const handleClose = () => {
        onClose();
    };

    return <BootstrapDialog
        TransitionComponent={Transition}
        onClose={handleClose}
        open={open ?? false}
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
