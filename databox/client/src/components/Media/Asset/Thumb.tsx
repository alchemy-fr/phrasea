import React, {PropsWithChildren} from 'react';
import {Box} from "@mui/material";
import {Theme} from "@mui/material/styles";

type Props = PropsWithChildren<{
    size: number;
}>;

export function createSizeTransition(theme: Theme) {
    return theme.transitions.create(['height', 'width'], {duration: 300});
}

const assetSx = (thumbSize: number) => (theme: Theme) => ({
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.palette.grey[100],
    'img': {
        maxWidth: '100%',
        maxHeight: '100%',
    },
    width: thumbSize,
    height: thumbSize,
    transition: createSizeTransition(theme),
});

export default function Thumb({
                                  children,
                                  size
                              }: Props) {

    return <Box sx={assetSx(size)}>
        {children}
    </Box>
}
