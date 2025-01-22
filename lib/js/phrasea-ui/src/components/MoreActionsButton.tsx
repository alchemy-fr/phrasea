import {IconButton} from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import DropdownActions from "./DropdownActions";
import type {DropdownActionsProps} from "./DropdownActions";

type Props = {
    children: DropdownActionsProps['children'];
};

export default function MoreActionsButton({children}: Props) {
    return (
        <DropdownActions
            anchorOrigin={{
                vertical: 'bottom',
                horizontal: 'right',
            }}
            mainButton={(props) => <IconButton
                {...props}
            >
                <MoreHorizIcon/>
            </IconButton>}
            children={children}
        />
    );
}
