import {PropsWithChildren} from 'react';
import {Box} from '@mui/material';

type Props = PropsWithChildren<{}>;

export default function Separator({children}: Props) {
    return (
        <Box
            sx={theme => ({
                my: 1,
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
                    flex: '0 0 20px',
                },
                ['&:not(:empty)::after']: {
                    ml: 1,
                    minWidth: 20,
                },
            })}
        >
            {children}
        </Box>
    );
}
