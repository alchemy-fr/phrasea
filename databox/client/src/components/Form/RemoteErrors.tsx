import React, {PropsWithChildren, ReactNode} from "react";
import {Alert, Box} from "@mui/material";
import nl2br from "react-nl2br";

type Props = {
    errors?: ReactNode[] | undefined;
};

export default function RemoteErrors({errors}: Props) {
    if (!errors || errors.length === 0) {
        return <></>
    }

    return <Box
        sx={{mt: 2}}
    >{errors.map((e, i) => <Alert key={i} severity="error">{nl2br(e)}</Alert>)}</Box>
}
