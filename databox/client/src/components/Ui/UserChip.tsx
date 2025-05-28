import {PropsWithChildren} from 'react';
import {Chip, ChipProps} from '@mui/material';

export const UserChip = ({
    children,
    ...props
}: PropsWithChildren<ChipProps>) => (
    <Chip {...props} color={'info'} label={children || props.label} />
);
