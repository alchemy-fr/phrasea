import {Backdrop, CircularProgress, Typography} from '@mui/material';
import {ReactNode} from 'react';

type Props = {
    open?: boolean;
    backdrop?: boolean;
    message?: ReactNode;
};

export default function FullPageLoader({
    open = true,
    backdrop = true,
    message,
}: Props) {
    return (
        <Backdrop
            sx={theme => ({
                color: backdrop ? theme.palette.common.white : undefined,
                zIndex: theme.zIndex.drawer + 1,
                flexDirection: 'column',
            })}
            open={open}
            invisible={!backdrop}
        >
            <div>
                <CircularProgress color="inherit" />
            </div>
            {message ? (
                <Typography
                    variant={'body1'}
                    sx={{
                        mt: 3,
                    }}
                >
                    {message}
                </Typography>
            ) : null}
        </Backdrop>
    );
}
