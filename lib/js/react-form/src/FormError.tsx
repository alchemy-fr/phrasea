import {CSSProperties, PropsWithChildren} from 'react';
import {Alert} from '@mui/material';

export default function FormError({
    children,
    style,
}: PropsWithChildren<{
    style?: CSSProperties;
}>) {
    if (!children) {
        return null;
    }

    return (
        <Alert severity={'error'} style={style}>
            {children}
        </Alert>
    );
}
