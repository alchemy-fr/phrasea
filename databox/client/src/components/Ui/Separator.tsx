import {PropsWithChildren} from "react";
import {Box} from "@mui/material";

type Props = PropsWithChildren<{}>;

export default function Separator({children}: Props) {
    return <Box sx={theme => ({
        display: 'flex',
        alignItems: 'center',
        fontSize: theme.typography.fontSize,
        ['&::before, &::after']: {
            content: '""',
            flex: 1,
            borderBottom: `1px solid ${theme.palette.divider}`,
        },
        ['&:not(:empty)::before']: {
            mr: 1,
            width: '10px',
            flex: '0 0 10px',
        },
        ['&:not(:empty)::after']: {
            ml: 1,
        },
    })}>
        {children}
    </Box>
}
