import React, {useContext} from 'react';
import {Box, IconButton} from '@mui/material';
import {SearchContext} from './SearchContext';
import SortBy from './Sorting/SortBy';
import {ZIndex} from '../../../themes/zIndex';
import GeoPointFilter from './GeoPointFilter';
import SearchAutoComplete from './SearchAutoComplete.tsx';
import FilterAltIcon from '@mui/icons-material/FilterAlt';
import SearchConditions from "./AQL/SearchConditions.tsx";

type Props = {};

export default function SearchBar({}: Props) {
    const search = useContext(SearchContext)!;
    const [filtersEnabled, setFiltersEnabled] = React.useState(false);

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
                <SearchAutoComplete />
                <IconButton
                    onClick={() => setFiltersEnabled(!filtersEnabled)}
                    color={filtersEnabled ? 'primary' : 'inherit'}
                    sx={{
                        ml: 1,
                    }}
                >
                    <FilterAltIcon />
                </IconButton>
                <GeoPointFilter />
                <SortBy />
            </Box>
            {(filtersEnabled || search.conditions.length > 0) && (
                <Box
                    sx={{
                        px: 1,
                    }}
                >
                    <SearchConditions
                        onDelete={search.removeCondition}
                        onUpdate={search.updateCondition}
                        conditions={search.conditions}
                    />
                </Box>
            )}
        </Box>
    );
}
