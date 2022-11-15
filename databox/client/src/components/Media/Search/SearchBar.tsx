import React, {FormEvent, useContext, useEffect, useRef, useState} from 'react';
import {styled} from "@mui/material/styles";
import {alpha, Box, Button, Chip, IconButton, InputBase} from "@mui/material";
import SearchIcon from '@mui/icons-material/Search';
import SearchFilters from "./SearchFilters";
import {useTranslation} from "react-i18next";
import {SearchContext} from "./SearchContext";
import {ResultContext} from "./ResultContext";
import FilterAltIcon from '@mui/icons-material/FilterAlt';
import FilterAltOffIcon from '@mui/icons-material/FilterAltOff';
import OrderByChip from "./OrderByChip";

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
    const resultContext = useContext(ResultContext);
    const [queryValue, setQueryValue] = useState('');
    const inputRef = useRef<HTMLInputElement>();
    const {t} = useTranslation();

    const hasFilters = search.attrFilters.length > 0 || search.orderBy;

    useEffect(() => {
        setQueryValue(search.query);
    }, [search.query]);

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        search.setQuery(queryValue, true);
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
                        disabled={search.query === queryValue && resultContext.loading}
                        type={'submit'}
                        variant={'contained'}
                    >
                        {t('search.search_button', 'Search')}
                    </Button>
                </Search>
            </form>
            <div>
                <IconButton
                    color={'primary'}
                >
                    {hasFilters ? <FilterAltOffIcon/> : <FilterAltIcon/>}
                </IconButton>
            </div>
            <div>
                Sort by
                {' '}
                {queryValue && <OrderByChip
                    t={'Score'}
                    a={'_score'}
                    w={1}
                />}
                {search.orderBy.map(o => <OrderByChip
                    {...o}
                    key={o.a}
                />)}
            </div>
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
