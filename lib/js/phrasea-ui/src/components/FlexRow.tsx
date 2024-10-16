import {PropsWithChildren} from 'react';
import {Box, BoxProps} from '@mui/material';

type Props = PropsWithChildren<BoxProps>;

export default function FlexRow({children, style, ...props}: Props) {
    return (
        <Box
            {...props}
            style={{
                display: 'flex',
                flexDirection: 'row',
                alignItems: 'center',
                ...(style ?? {}),
            }}
        >
            {children}
        </Box>
    );
}
