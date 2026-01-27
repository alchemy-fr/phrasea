import React from 'react';
import {Box} from '@mui/material';

type Props = {
    index: number;
    total: number;
};

export default function AssetIndex({index, total}: Props) {
    return (
        <Box
            sx={{
                userSelect: 'none',
                minWidth: 70,
                whiteSpace: 'nowrap',
                zIndex: 1,
                textAlign: 'center',
            }}
        >
            {index + 1}&nbsp;/&nbsp;{total}
        </Box>
    );
}
