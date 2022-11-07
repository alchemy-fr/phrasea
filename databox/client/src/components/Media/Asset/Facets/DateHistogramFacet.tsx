import React, {useCallback, useContext, useEffect, useMemo, useState} from 'react';
import {FacetRowProps, FacetType} from "../Facets";
import {Box, Button, Slider} from "@mui/material";
import moment from "moment";
import {SearchContext} from "../../Search/SearchContext";

function getValueText(value: number): string {
    return moment(value * 1000).format('ll');
}

export default function DateHistogramFacet({
                                               facet,
                                               name,
                                           }: FacetRowProps) {
    const {attrFilters, setAttrFilter, removeAttrFilter} = useContext(SearchContext);
    const attrFilterIndex = attrFilters.findIndex(_f => _f.a === name);
    const attrFilter = attrFilterIndex >= 0 ? attrFilters[attrFilterIndex] : undefined;

    const [min, max] = useMemo(() => {
        const min = facet.buckets.reduce(function (prev, curr) {
            return prev.key < curr.key ? prev : curr;
        });
        const max = facet.buckets.reduce(function (prev, curr) {
            return prev.key > curr.key ? prev : curr;
        });

        return [min.key as number, max.key as number];
    }, [facet.buckets]);

    const [value, setValue] = useState<[number, number]>([min, max]);

    useEffect(() => {
        if (attrFilter) {
            setValue(attrFilter.v as [number, number]);
        } else {
            setValue([min, max]);
        }
    }, [min, max, attrFilter]);

    const handleChange = useCallback((event: Event, newValue: number | number[]) => {
        setValue(newValue as [number, number]);
    }, [setValue]);

    const handleChangeCommitted = useCallback((event: React.SyntheticEvent | Event, newValue: number | number[]) => {
        setAttrFilter(name, newValue as [number, number], facet.meta.title, FacetType.DateRange);
    }, [facet]);

    const hasRange = max > min;

    const marks = useMemo(() => [
        {
            value: min,
            label: getValueText(min),
        },
        {
            value: max,
            label: getValueText(max),
        }
    ], [min, max]);

    return <Box
        sx={{
            px: 6,
            py: 1,
        }}
    >
        {!hasRange && attrFilterIndex >= 0 && <div>
            <Button
                onClick={() => removeAttrFilter(attrFilterIndex)}
            >
                Reset
            </Button>
        </div>}
        {hasRange && <Slider
            getAriaLabel={() => 'Date range'}
            value={value}
            onChangeCommitted={handleChangeCommitted}
            onChange={handleChange}
            valueLabelDisplay="auto"
            getAriaValueText={getValueText}
            valueLabelFormat={getValueText}
            step={86400}
            marks={marks}
            min={min}
            max={max}
            disableSwap
        />}
    </Box>
}
