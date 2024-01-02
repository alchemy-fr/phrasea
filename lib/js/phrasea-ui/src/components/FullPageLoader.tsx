import {Backdrop, CircularProgress} from "@mui/material";

type Props = {
    open?: boolean;
    backdrop?: boolean;
};

export default function FullPageLoader({
    open = true,
    backdrop = true,
}: Props) {
    return <Backdrop
        sx={{
            color: '#fff',
            zIndex: (theme) => theme.zIndex.drawer + 1
        }}
        open={open}
        invisible={!backdrop}
    >
        <CircularProgress/>
    </Backdrop>
}
