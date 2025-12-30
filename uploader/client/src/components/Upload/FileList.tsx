import {Box} from '@mui/material';
import {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{}>;

export default function FileList({children}: Props) {
    return (
        <Box
            sx={{
                display: 'flex',
                flexDirection: 'row',
                gap: 1,
                flexWrap: 'wrap',
            }}
        >
            {children}
        </Box>
    );
}
