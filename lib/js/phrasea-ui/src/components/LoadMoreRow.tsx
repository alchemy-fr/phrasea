import {Box, Button} from '@mui/material';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import React from 'react';
import {useTranslation} from 'react-i18next';

type Props = {
    hasMore?: boolean;
    onClick: () => any;
    loading: boolean;
};

export default function LoadMoreRow({hasMore, loading, onClick}: Props) {
    const {t} = useTranslation();

    if (!hasMore) {
        return null;
    }

    return (
        <>
            <Box
                sx={{
                    mt: 2,
                    display: 'flex',
                    justifyContent: 'center',
                    width: '100%',
                }}
            >
                <Button
                    variant={'outlined'}
                    loading={loading}
                    disabled={loading}
                    onClick={onClick}
                    startIcon={<KeyboardArrowDownIcon />}
                >
                    {t('lib.ui.load_more.label', 'Load more')}
                </Button>
            </Box>
        </>
    );
}
