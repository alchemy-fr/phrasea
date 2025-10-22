import {InputAdornment, Stack, TextField} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import IconButton from '@mui/material/IconButton';
import SearchIcon from '@mui/icons-material/Search';

type Props = {
    name: string;
    searchQuery: string;
    setSearchQuery: (query: string) => void;
    loading: boolean;
    searchHandler: () => Promise<any>;
};

export default function SearchBar({
    name,
    searchQuery,
    setSearchQuery,
    loading,
    searchHandler,
}: Props) {
    const {t} = useTranslation();
    return (
        <Stack
            sx={{p: 1}}
            component={'form'}
            direction={'row'}
            onSubmit={e => {
                e.preventDefault();
                searchHandler();
            }}
        >
            <TextField
                fullWidth={true}
                name={name}
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                size={'small'}
                type={'search'}
                placeholder={t('common.search.placeholder', 'Search…')}
                InputProps={{
                    endAdornment: (
                        <InputAdornment position="end">
                            <IconButton
                                disabled={!searchQuery || loading}
                                aria-label={t('common.search.submit', 'Search')}
                                edge="end"
                                type={'submit'}
                            >
                                <SearchIcon />
                            </IconButton>
                        </InputAdornment>
                    ),
                }}
            />
        </Stack>
    );
}
