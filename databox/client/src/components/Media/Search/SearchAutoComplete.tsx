import React, {
    FormEvent,
    MouseEventHandler,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import SearchIcon from '@mui/icons-material/Search';
import {alpha, Button, InputBase} from '@mui/material';
import AutoComplete from './AutoComplete.tsx';
import {GetSources} from '@algolia/autocomplete-core';
import {getSearchSuggestions, SearchSuggestion} from '../../../api/asset.ts';
import {ResultContext} from './ResultContext.tsx';
import {useTranslation} from 'react-i18next';
import {SearchContext} from './SearchContext.tsx';
import {styled} from '@mui/material/styles';

type Props = {};

export default function SearchAutoComplete({}: Props) {
    const search = useContext(SearchContext);

    const resultContext = useContext(ResultContext);
    const [queryValue, setQueryValue] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
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
            },
        ];
    }, [search]);
    return (
        <>
            <AutoComplete getSources={getSources} queryValue={queryValue}>
                {autocomplete => {
                    return (
                        <form
                            {...(autocomplete.getFormProps({
                                inputElement: inputRef.current,
                            }) as any)}
                            onSubmit={(e: FormEvent<HTMLFormElement>) => {
                                autocomplete.setIsOpen(false);
                                onSubmit(e);
                            }}
                        >
                            <Search>
                                <SearchIconWrapper>
                                    <SearchIcon />
                                </SearchIconWrapper>
                                <StyledInputBase
                                    autoFocus={true}
                                    type={'search'}
                                    onChange={e =>
                                        setQueryValue(e.target.value)
                                    }
                                    inputRef={inputRef}
                                    onClick={onClick}
                                    placeholder="Search…"
                                    onKeyDown={e => e.stopPropagation()} // Prevent Ctrl + A propagation
                                    onKeyPress={e => e.stopPropagation()} // Prevent Ctrl + A propagation
                                    inputProps={{
                                        'aria-label': 'search',
                                        ...(autocomplete.getInputProps({
                                            inputElement: null,
                                            onBlur: () => {
                                                autocomplete.setIsOpen(false);
                                            },
                                        }) as any),
                                    }}
                                />
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
                    );
                }}
            </AutoComplete>
        </>
    );
}

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
