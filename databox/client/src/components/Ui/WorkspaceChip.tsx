import {PropsWithChildren} from 'react';
import {Chip, ChipProps} from '@mui/material';
import {Workspace} from '../../types.ts';

type Props = {
    workspace?: Workspace;
} & PropsWithChildren<ChipProps>;

export const WorkspaceChip = ({
    children,
    workspace,
    label,
    ...props
}: Props) => (
    <Chip
        {...props}
        color={'primary'}
        label={
            children || label || workspace?.nameTranslated || workspace?.name
        }
    />
);
