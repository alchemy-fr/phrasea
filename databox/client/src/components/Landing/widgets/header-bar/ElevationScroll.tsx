import {useScrollTrigger} from '@mui/material';
import React from 'react';

type Props = {
    children?: React.ReactElement<{elevation?: number}>;
};

export default function ElevationScroll(props: Props) {
    const {children} = props;
    // Note that you normally won't need to set the window ref as useScrollTrigger
    // will default to window.
    // This is only being set here because the demo is in an iframe.
    const trigger = useScrollTrigger({
        disableHysteresis: true,
        threshold: 0,
    });

    return children
        ? React.cloneElement(children, {
              elevation: trigger ? 4 : 0,
          })
        : null;
}
