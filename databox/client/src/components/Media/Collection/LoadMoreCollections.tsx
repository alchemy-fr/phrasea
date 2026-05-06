import {ListItemLoadingIcon} from '@alchemy/phrasea-framework';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import {ListItemButton} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    onLoadMore: () => void;
    loading: boolean;
};

export default function LoadMoreCollections({onLoadMore, loading}: Props) {
    const {t} = useTranslation();
    return (
        <ListItemButton onClick={onLoadMore} disabled={loading}>
            <ListItemLoadingIcon loading={loading}>
                <MoreHorizIcon />
            </ListItemLoadingIcon>
            {t('pagination.load_more_collections', `Load more collections`)}
        </ListItemButton>
    );
}
