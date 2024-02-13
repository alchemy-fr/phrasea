import React, {FormEvent, MouseEventHandler, useContext, useEffect, useRef, useState} from 'react';
import {styled} from '@mui/material/styles';
import {alpha, Box, Button, InputBase} from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';
import SearchFilters from './SearchFilters';
import {useTranslation} from 'react-i18next';
import {SearchContext} from './SearchContext';
import {ResultContext} from './ResultContext';
import SortBy from './Sorting/SortBy';
import {zIndex} from '../../../themes/zIndex';
import GeoPointFilter from './GeoPointFilter';
import AutoComplete from "./AutoComplete.tsx";
import {getSearchSuggestions, SearchSuggestion} from "../../../api/asset.ts";
import {GetSources} from "@algolia/autocomplete-core";

type Props = {};

const Search = styled('div')(({theme}) => ({
    'position': 'relative',
    'borderRadius': theme.shape.borderRadius,
    'backgroundColor': alpha(theme.palette.common.white, 0.35),
    '&:hover': {
        backgroundColor: alpha(theme.palette.common.white, 0.65),
    },
    'display': 'flex',
    'alignItems': 'center',
    'margin': theme.spacing(1),
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
    'color': 'inherit',
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

    useEffect(() => {
        setQueryValue(search.query);
    }, [search.query]);

    const onClick: MouseEventHandler<HTMLInputElement> = () => {
        if (search.query) {
            setTimeout(() => {
                if (inputRef.current?.value === '') {
                    search.setQuery('', true);
                }
            }, 10);
        }
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        search.setQuery(queryValue, true);
    };

    const getSources = React.useCallback<GetSources<SearchSuggestion>>(() => {
        return [
            {
                sourceId: 'items',
                onSelect: ({item, setQuery}) => {
                    const newQuery = `"${item.name}"`;
                    setQuery(newQuery);
                    setQueryValue(newQuery);
                    search.setQuery(newQuery, true);
                },
                getItems({query}) {
                    return getSearchSuggestions(query).then(r => {
                        console.log('ES Debug', r.debug);
                        console.log('ES Query', JSON.stringify(r.debug.query));

                        return r.result;
                    });
                },
            }
        ];
    }, [search]);

    return (
        <Box
            sx={{
                bgcolor: 'secondary.main',
                zIndex: zIndex.toolbar,
                position: 'relative',
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
                        <AutoComplete
                            getSources={getSources}
                        >
                            {(props) => <StyledInputBase
                                autoFocus={true}
                                type={'search'}
                                value={queryValue}
                                onChange={e => setQueryValue(e.target.value)}
                                inputRef={inputRef}
                                onClick={onClick}
                                placeholder="Search…"
                                onKeyDown={e => e.stopPropagation()} // Prevent Ctrl + A propagation
                                onKeyPress={e => e.stopPropagation()} // Prevent Ctrl + A propagation
                                inputProps={{
                                    'aria-label': 'search',
                                    ...props,
                                    value: queryValue,
                                }}
                            />}
                        </AutoComplete>
                        <Button
                            disabled={
                                search.query === queryValue &&
                                resultContext.loading
                            }
                            type={'submit'}
                            variant={'contained'}
                        >
                            {t('search.search_button', 'Search')}
                        </Button>
                    </Search>
                </form>
                <GeoPointFilter/>
                <SortBy/>
            </Box>
            {search.attrFilters.length > 0 && (
                <Box
                    sx={{
                        px: 1,
                    }}
                >
                    <SearchFilters
                        onDelete={search.removeAttrFilter}
                        onInvert={search.invertAttrFilter}
                        filters={search.attrFilters}
                    />
                </Box>
            )}
        </Box>
    );
}
