import {PropsWithChildren, ReactNode} from "react";
import {Box} from "@mui/material";

type Props = PropsWithChildren<{
    startIcon: ReactNode;
}>;

export default function IconFormLabel({
    startIcon,
    children,
}: Props) {
    return <>
        <Box sx={theme => ({
            marginRight: theme.spacing(1),
            display: 'inline-block',
            verticalAlign: 'middle'
        })}>
            {startIcon}
        </Box>
        {children}
    </>
}
