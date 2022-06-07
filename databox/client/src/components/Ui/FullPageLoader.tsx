import React from "react";
import {Backdrop, CircularProgress} from "@mui/material";

export default function FullPageLoader() {
    return <Backdrop
        sx={theme => ({
            color: theme.palette.common.white,
            zIndex: theme.zIndex.drawer + 1
        })}
        open={true}
    >
        <CircularProgress color="inherit" />
    </Backdrop>
}
