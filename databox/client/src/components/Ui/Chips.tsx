import {Chip, ChipProps} from '@mui/material';
import {PropsWithChildren} from 'react';

export const CollectionChip = ({
    children,
    ...props
}: PropsWithChildren<ChipProps>) => (
    <Chip
        {...props}
        sx={theme => ({
            bgcolor: theme.palette.grey[300],
            color: theme.palette.grey[900],
        })}
        label={children || props.label}
    />
);

export const WorkspaceChip = ({
    children,
    ...props
}: PropsWithChildren<ChipProps>) => (
    <Chip {...props} color={'primary'} label={children || props.label} />
);
