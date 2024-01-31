import MoreHorizIcon from "@mui/icons-material/MoreHoriz";
import {CircularProgress, ListItemButton, ListItemIcon} from "@mui/material";

type Props = {
    onLoadMore: () => void;
    loading: boolean;
};

export default function LoadMoreCollections({
    onLoadMore,
    loading,
}: Props) {

    return <ListItemButton
        onClick={onLoadMore}
        disabled={loading}
    >
        <ListItemIcon sx={{
            minWidth: 35,
        }}>
            {loading ? <CircularProgress size={20}/> : <MoreHorizIcon/>}
        </ListItemIcon>
        Load more collections
    </ListItemButton>
}
