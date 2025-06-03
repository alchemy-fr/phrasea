import {Chip, ChipProps} from '@mui/material';
import {User} from '../../types.ts';

type Props = {
    user: User;
} & ChipProps;

export const UserChip = ({user, ...props}: Props) => (
    <Chip
        {...props}
        color={!user.removed ? 'info' : 'error'}
        label={user.username}
    />
);
