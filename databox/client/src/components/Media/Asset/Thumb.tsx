import React, {PropsWithChildren} from 'react';
import {Box} from "@mui/material";
import {Theme} from "@mui/material/styles";

type Props = PropsWithChildren<{
    size: number;
}>;

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
});

export default function Thumb({
                                  children,
                                  size
                              }: Props) {

    return <Box sx={assetSx(size)}>
        {children}
    </Box>
}
