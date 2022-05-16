import React, {useContext, useEffect, useRef, useState} from 'react';
import {styled} from "@mui/material/styles";
import {alpha, Box, Button, InputBase} from "@mui/material";
import SearchIcon from '@mui/icons-material/Search';
import SearchFilters from "./SearchFilters";
import {ResultContext} from "./ResultContext";
import {useTranslation} from "react-i18next";
import {SearchContext} from "./SearchContext";

type Props = {};

const Search = styled('div')(({theme}) => ({
    position: 'relative',
    borderRadius: theme.shape.borderRadius,
    backgroundColor: alpha(theme.palette.common.white, 0.35),
    '&:hover': {
        backgroundColor: alpha(theme.palette.common.white, 0.65),
    },
    display: 'flex',
    alignItems: 'center',
    width: '100%',
    margin: theme.spacing(1),
    [theme.breakpoints.up('sm')]: {
        width: 'fit-content',
    },
}));

const SearchIconWrapper = styled('div')(({theme}) => ({
    padding: theme.spacing(0, 2),
    height: '100%',
    position: 'absolute',
    pointerEvents: 'none',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
}));

const StyledInputBase = styled(InputBase)(({theme}) => ({
    color: 'inherit',
    '& .MuiInputBase-input': {
        padding: theme.spacing(1, 1, 1, 0),
        // vertical padding + font size from searchIcon
        paddingLeft: `calc(1em + ${theme.spacing(4)})`,
        width: '100%',
    },
}));


export default function SearchBar({}: Props) {
    const search = useContext(SearchContext);
    const [queryValue, setQueryValue] = useState('');
    const inputRef = useRef<HTMLInputElement>();
    const {t} = useTranslation();

    useEffect(() => {
        setQueryValue(search.query);
    }, [search.query]);

    const onSubmit = () => {
        search.setQuery(queryValue)
    }

    return <Box
        sx={{
            bgcolor: 'secondary.main',
        }}
    >
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
            }}
        >
            <form onSubmit={onSubmit}>
                <Search>
                    <SearchIconWrapper>
                        <SearchIcon/>
                    </SearchIconWrapper>
                    <StyledInputBase
                        autoFocus={true}
                        value={queryValue}
                        onChange={(e) => setQueryValue(e.target.value)}
                        inputRef={inputRef}
                        placeholder="Search…"
                        inputProps={{'aria-label': 'search'}}
                    />
                    <Button
                        disabled={search.query === queryValue}
                        type={'submit'}
                        variant={'contained'}
                    >
                        {t('search.search_button', 'Search')}
                    </Button>
                </Search>
            </form>
        </Box>
        {search.attrFilters.length > 0 && <Box sx={{p: 1}}>
            <SearchFilters
                onDelete={search.removeAttrFilter}
                onInvert={search.invertAttrFilter}
                filters={search.attrFilters}
            />
        </Box>}
    </Box>
}
