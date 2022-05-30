import React, {PropsWithChildren, ReactNode} from "react";
import {Alert, Box} from "@mui/material";

type Props = {
    errors?: ReactNode[] | undefined;
};

export default function RemoteErrors({errors}: Props) {
    if (!errors || errors.length === 0) {
        return <></>
    }

    return <Box
        sx={{mt: 2}}
    >{errors.map((e, i) => <Alert key={i} severity="error">{e}</Alert>)}</Box>
}
