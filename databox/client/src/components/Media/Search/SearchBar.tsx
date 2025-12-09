import React, {FormEvent, MouseEventHandler, useContext, useRef} from 'react';
import {Box, Button, InputBase, Paper} from '@mui/material';
import {SearchContext} from './SearchContext';
import SortBy from './Sorting/SortBy';
import {ZIndex} from '../../../themes/zIndex';
import GeoPointFilter from './GeoPointFilter';
import SearchConditions from './AQL/SearchConditions.tsx';
import {FlexRow} from '@alchemy/phrasea-ui';
import {useSuggest} from './Suggest/useSuggest.tsx';
import SearchIcon from '@mui/icons-material/Search';
import {ResultContext} from './ResultContext.tsx';
import {useTranslation} from 'react-i18next';
import SuggestPopover from './Suggest/SuggestPopover.tsx';

type Props = {};

export default function SearchBar({}: Props) {
    const {t} = useTranslation();
    const search = useContext(SearchContext)!;
    const resultContext = useContext(ResultContext)!;
    const inputRef = useRef<HTMLInputElement>(null);
    const {inputQuery, setInputQuery} = search;
    const placeholder = t('search_auto_complete.search', `Search…`);
    const usedSuggest = useSuggest({
        search,
        placeholder,
    });
    const {autocomplete} = usedSuggest;

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        search.setQuery(inputQuery.current || '', true);
    };

    const onClick: MouseEventHandler<HTMLInputElement> = () => {
        if (search.query) {
            setTimeout(() => {
                if (inputRef.current?.value === '') {
                    search.setQuery('', true);
                }
            }, 10);
        }
    };

    return (
        <Box
            sx={{
                zIndex: ZIndex.toolbar,
                position: 'relative',
            }}
        >
            <Paper
                sx={_theme => ({
                    my: 1,
                    mx: 'auto',
                    p: 1,
                    width: 'fit-content',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                })}
            >
                <FlexRow>
                    <form
                        {...(autocomplete.getFormProps({
                            inputElement: inputRef.current,
                        }) as any)}
                        onSubmit={(e: FormEvent<HTMLFormElement>) => {
                            autocomplete.setIsOpen(false);
                            onSubmit(e);
                        }}
                    >
                        <div
                            className={'aa-Autocomplete'}
                            {...(usedSuggest.autocomplete.getRootProps(
                                {}
                            ) as any)}
                        >
                            <Box
                                sx={theme => ({
                                    zIndex: theme.zIndex.modal - 1,
                                    mb: 1,
                                    position: 'relative',
                                    display: 'flex',
                                    alignItems: 'center',
                                    flexDirection: 'row',
                                })}
                            >
                                <SearchIcon
                                    sx={{
                                        mx: 1,
                                    }}
                                />
                                <InputBase
                                    autoFocus={true}
                                    type={'search'}
                                    onChange={e =>
                                        setInputQuery(e.target.value)
                                    }
                                    inputRef={inputRef}
                                    onClick={onClick}
                                    placeholder={placeholder}
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
                                    sx={{
                                        flexGrow: 1,
                                        width: 'auto',
                                    }}
                                />
                            </Box>
                            <SuggestPopover usedSuggest={usedSuggest} />
                        </div>

                        <div
                            style={{
                                position: 'relative',
                                display: 'flex',
                                alignItems: 'center',
                                flexDirection: 'row',
                            }}
                        >
                            <div
                                style={{
                                    flexGrow: 1,
                                }}
                            >
                                <GeoPointFilter />
                                <SortBy />

                                <SearchConditions search={search} />
                            </div>

                            <Button
                                sx={{
                                    ml: 1,
                                }}
                                disabled={
                                    search.query === inputQuery.current &&
                                    resultContext.loading
                                }
                                type={'submit'}
                                variant={'contained'}
                            >
                                {t('search.search_button', 'Search')}
                            </Button>
                        </div>
                    </form>
                </FlexRow>
            </Paper>
        </Box>
    );
}
