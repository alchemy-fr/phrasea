import {Backdrop, CircularProgress} from '@mui/material';

export default function FullPageLoader() {
    return (
        <Backdrop
            sx={theme => ({
                color: theme.palette.background.default,
                zIndex: theme.zIndex.drawer + 1,
            })}
            open={true}
        >
            <CircularProgress color="inherit" />
        </Backdrop>
    );
}
