import {useScrollTrigger} from '@mui/material';
import React from 'react';
import {ElevationConstants} from './types.ts';

type Props = {
    children: (props: {elevated: boolean}) => React.ReactElement;
};

export default function ElevationScroll({children}: Props) {
    const trigger = useScrollTrigger({
        disableHysteresis: true,
        threshold: 0,
        target:
            document.getElementById(ElevationConstants.ContainerId) ||
            undefined,
    });

    return children({
        elevated: trigger,
    });
}
