import {resolveSx} from '@alchemy/core';
import {Box, Container, SxProps} from '@mui/material';
import {PropsWithChildren} from 'react';

type Props = PropsWithChildren<{
    sx?: SxProps;
}>;

export default function PageWrapper({children, sx}: Props) {
    return (
        <>
            <Box
                sx={theme => ({
                    '[contenteditable="false"]:focus': {
                        outline: 'none',
                    },
                    'position': 'relative',
                    'mb': 4,
                    '--black': theme.palette.common.black,
                    '--white': theme.palette.common.white,
                    '--primary': theme.palette.primary.main,
                    '--secondary': theme.palette.secondary.main,
                    '--divider': theme.palette.divider,
                    ...resolveSx(sx, theme),
                })}
            >
                <Container>{children}</Container>
            </Box>
        </>
    );
}
