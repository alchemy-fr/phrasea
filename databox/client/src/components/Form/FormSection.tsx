import {PropsWithChildren} from 'react';
import {Box} from '@mui/material';

export default function FormSection({children}: PropsWithChildren<{}>) {
    return (
        <Box
            sx={theme => ({
                borderTop: `1px solid ${theme.palette.divider}`,
                my: 3,
                pt: 3,
            })}
        >
            {children}
        </Box>
    );
}
