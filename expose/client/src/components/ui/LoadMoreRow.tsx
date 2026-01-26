import {Box, Button} from '@mui/material';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {NormalizedCollectionResponse} from '@alchemy/api';

type Props<T> = {
    data?: NormalizedCollectionResponse<T>;
    load: (nextUrl?: string) => any;
    loading: boolean;
};

export default function LoadMoreRow<T>({loading, load, data}: Props<T>) {
    const {t} = useTranslation();

    return (
        <>
            {data?.next ? (
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
                        onClick={() => {
                            load(data.next!);
                        }}
                        startIcon={<KeyboardArrowDownIcon />}
                    >
                        {t('publication.load_more', 'Load more')}
                    </Button>
                </Box>
            ) : null}
        </>
    );
}
