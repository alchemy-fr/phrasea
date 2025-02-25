import {CSSProperties, PropsWithChildren} from 'react';
import {Box, BoxProps} from '@mui/material';

type Props = PropsWithChildren<
    {direction?: CSSProperties['flexDirection']} & BoxProps
>;

export default function FlexRow({
    children,
    style,
    direction = 'row',
    ...props
}: Props) {
    return (
        <Box
            {...props}
            style={{
                display: 'flex',
                flexDirection: direction,
                alignItems: 'center',
                ...(style ?? {}),
            }}
        >
            {children}
        </Box>
    );
}
