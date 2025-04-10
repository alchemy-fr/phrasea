import {IconButton} from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import DropdownActions from './DropdownActions';
import type {DropdownActionsProps} from './DropdownActions';

type Props = Omit<DropdownActionsProps, 'mainButton'>;

export default function MoreActionsButton(dropdownActionsProps: Props) {
    return (
        <DropdownActions
            anchorOrigin={{
                vertical: 'bottom',
                horizontal: 'right',
            }}
            mainButton={props => (
                <IconButton {...props}>
                    <MoreHorizIcon />
                </IconButton>
            )}
            {...dropdownActionsProps}
        />
    );
}
