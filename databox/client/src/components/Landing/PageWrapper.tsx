import {resolveSx} from '@alchemy/core';
import {Box, Container, SxProps} from '@mui/material';
import {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{
    sx?: SxProps;
    id?: string;
}>;

export default function PageWrapper({children, sx, id}: Props) {
    return (
        <>
            <Box
                id={id}
                sx={theme => ({
                    '[contenteditable="false"]:focus': {
                        outline: 'none',
                    },
                    'position': 'relative',
                    'mb': 4,
                    ...resolveSx(sx, theme),
                    '--black': theme.palette.common.black,
                    '--white': theme.palette.common.white,
                    '--primary': theme.palette.primary.main,
                    '--secondary': theme.palette.secondary.main,
                    '--divider': theme.palette.divider,
                })}
            >
                <Container>{children}</Container>
            </Box>
        </>
    );
}
