import {CircularProgress, ListItemIcon, ListItemIconProps} from '@mui/material';

type Props = {
    loading?: boolean;
} & ListItemIconProps;

export default function ListItemLoadingIcon({loading, children, ...props}: Props) {
    return (
        <ListItemIcon
            sx={{
                minWidth: 35,
            }}
            {...props}
        >
            {loading ? <CircularProgress size={20} /> : children}
        </ListItemIcon>
    );
}
