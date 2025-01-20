import {IconButton} from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import DropdownActions, {DropdownActionsProps} from "./DropdownActions";

type Props = {
    children: DropdownActionsProps['children'];
};

export default function MoreActionsButton({children}: Props) {
    return (
        <DropdownActions
            mainButton={(props) => <IconButton
                {...props}
            >
                <MoreHorizIcon/>
            </IconButton>}
            children={children}
        />
    );
}
