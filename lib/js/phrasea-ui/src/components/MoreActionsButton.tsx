import {IconButton, IconButtonProps} from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import DropdownActions from './DropdownActions';
import {DropdownActionsProps} from '../types';

type Props = {
    vertical?: boolean;
    iconButtonProps?: IconButtonProps;
} & Omit<DropdownActionsProps, 'mainButton'>;

export default function MoreActionsButton({vertical, iconButtonProps, ...dropdownActionsProps}: Props) {
    return (
        <DropdownActions
            anchorOrigin={{
                vertical: 'bottom',
                horizontal: 'right',
            }}
            mainButton={props => (
                <IconButton {...props} {...iconButtonProps}>
                    {vertical ? <MoreVertIcon /> : <MoreHorizIcon />}
                </IconButton>
            )}
            {...dropdownActionsProps}
        />
    );
}
