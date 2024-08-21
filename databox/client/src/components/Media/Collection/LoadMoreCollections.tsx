import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import {CircularProgress, ListItemButton, ListItemIcon} from '@mui/material';
import { useTranslation } from 'react-i18next';

type Props = {
    onLoadMore: () => void;
    loading: boolean;
};

export default function LoadMoreCollections({onLoadMore, loading}: Props) {
    const {t} = useTranslation();
    return (
        <ListItemButton onClick={onLoadMore} disabled={loading}>
            <ListItemIcon
                sx={{
                    minWidth: 35,
                }}
            >
                {loading ? <CircularProgress size={20} /> : <MoreHorizIcon />}
            </ListItemIcon>
            {t('pagination.load_more_collections', `Load more collections`)}</ListItemButton>
    );
}
