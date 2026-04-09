import React, {PropsWithChildren} from 'react';
import {Box, BoxProps} from '@mui/material';

type ColorBoxProps = PropsWithChildren<{
    color: string;
    width?: number;
    height?: number;
    borderWidth?: number;
}> &
    BoxProps;

export function ColorBox({
    color,
    width = 30,
    height = 22,
    borderWidth = 1,
    children,
    style,
    ...divProps
}: ColorBoxProps) {
    return (
        <Box
            sx={theme => ({
                width,
                height,
                backgroundColor: color,
                border: `${borderWidth}px solid ${theme.palette.text.primary}`,
                borderRadius: '5px',
                ...(style || {}),
            })}
            {...divProps}
        >
            {children}
        </Box>
    );
}
