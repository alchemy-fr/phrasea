import {useContext,} from 'react';
import {Box} from '@mui/material';
import SearchFilters from './SearchFilters';
import {SearchContext} from './SearchContext';
import SortBy from './Sorting/SortBy';
import {ZIndex} from '../../../themes/zIndex';
import GeoPointFilter from './GeoPointFilter';
import SearchAutoComplete from "./SearchAutoComplete.tsx";

type Props = {};

export default function SearchBar({}: Props) {
    const search = useContext(SearchContext);

    return (
        <Box
            sx={{
                bgcolor: 'secondary.main',
                zIndex: ZIndex.toolbar,
                position: 'relative',
            }}
        >
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                }}
            >
                <SearchAutoComplete/>
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
