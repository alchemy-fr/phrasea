import {PropsWithChildren} from "react";
import {Box} from "@mui/material";

export default function FormError({children}: PropsWithChildren<{}>) {
    return <Box
        sx={{
            color: 'error.main',
        }}
    >
        {children}
    </Box>
}
