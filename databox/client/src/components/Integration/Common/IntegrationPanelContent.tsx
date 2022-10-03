import React, {PropsWithChildren} from 'react';
import {Box} from "@mui/material";

type Props = PropsWithChildren;

export default function IntegrationPanelContent({children}: Props) {

    return <Box
        sx={{
            p: 2,
        }}
    >
        {children}
    </Box>
}
