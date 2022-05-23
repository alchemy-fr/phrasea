import React, {PropsWithChildren} from 'react';
import {Box} from "@mui/material";
import {alpha, Theme} from "@mui/material/styles";

type Props = PropsWithChildren<{
    size: number;
    selected?: boolean;
}>;

export function createSizeTransition(theme: Theme) {
    return theme.transitions.create(['height', 'width'], {duration: 300});
}

const assetSx = (thumbSize: number) => (theme: Theme) => ({
    display: 'flex',
    alignItems: 'center',
    position: 'relative',
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
                                  selected,
                                  children,
                                  size
                              }: Props) {

    return <Box sx={assetSx(size)}>
        {selected && <Box
            sx={theme => ({
                position: 'absolute',
                top: 0,
                left: 0,
                bottom: 0,
                right: 0,
                backgroundColor: alpha(theme.palette.primary.main, 0.3),
            })}
        />}
        {children}
    </Box>
}
