import React, {
    ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import {FacetGroupProps} from '../Facets';
import {
    Box,
    Button,
    ListItem,
    ListItemSecondaryAction,
    ListItemText,
    Slider,
    useTheme,
} from '@mui/material';
import moment from 'moment';
import {SearchContext} from '../../Search/SearchContext';
import {AttributeType} from '../../../../api/attributes';
import {useTranslation} from 'react-i18next';

export default function DateHistogramFacet({facet, name}: FacetGroupProps) {
    const {t} = useTranslation();
    const {attrFilters, setAttrFilter, removeAttrFilter} =
        useContext(SearchContext)!;
    const attrFilterIndex = attrFilters.findIndex(_f => _f.a === name);
    const attrFilter =
        attrFilterIndex >= 0 ? attrFilters[attrFilterIndex] : undefined;
    const theme = useTheme();
    const histogramHeight = 50;
    const displayTime = Boolean(
        facet.meta.type === AttributeType.DateTime &&
            /^\d+[hms]$/.test(facet.interval ?? '')
    );

    const min = facet.buckets[0].key as number;
    const max = facet.buckets[facet.buckets.length - 1].key as number;
    const step =
        facet.buckets.length >= 2
            ? (facet.buckets[1].key as number) -
              (facet.buckets[0].key as number)
            : undefined;

    const getValueText = React.useCallback(
        (value: number): ReactNode => {
            const m = moment(value * 1000);

            return (
                <>
                    {m.format('ll')}
                    {displayTime ? (
                        <>
                            <br />
                            {m.format('HH:mm:ss')}
                        </>
                    ) : null}
                </>
            );
        },
        [displayTime]
    );

    const [value, setValue] = useState<[number, number]>([min, max]);

    useEffect(() => {
        if (attrFilter) {
            setValue(attrFilter.v as [number, number]);
        } else {
            setValue([min, max]);
        }
    }, [min, max, attrFilter]);

    const handleChange = useCallback(
        (_event: Event, newValue: number | number[]) => {
            setValue(newValue as [number, number]);
        },
        [setValue]
    );

    const handleChangeCommitted = useCallback(
        (_event: React.SyntheticEvent | Event, newValue: number | number[]) => {
            if (step) {
                (newValue as [number, number])[1] += step;
            }
            setAttrFilter(
                name,
                facet.meta.type,
                newValue as [number, number],
                facet.meta.title,
                facet.meta.widget
            );
        },
        [facet, step]
    );

    const hasRange = max > min;

    const colorActive = theme.palette.secondary.main;
    const greyInactive = theme.palette.grey[500];

    const marks = useMemo(() => {
        const maxCount = facet.buckets.reduce(function (prev, curr) {
            return prev.doc_count > curr.doc_count ? prev : curr;
        }).doc_count;
        const ratio = histogramHeight / maxCount;

        return facet.buckets.map(b => ({
            value: b.key as number,
            label: (
                <div
                    style={{
                        marginTop:
                            -b.doc_count * ratio +
                            (b.doc_count > 1 ? 0 : 5) -
                            18 -
                            4,
                        height: b.doc_count * ratio + (b.doc_count > 1 ? 5 : 0),
                        width: 10,
                        backgroundColor:
                            value[0] <= (b.key as number) &&
                            value[1] >= (b.key as number)
                                ? colorActive
                                : greyInactive,
                    }}
                />
            ),
        }));
    }, [facet.buckets, colorActive, greyInactive, value]);

    return (
        <Box
            style={{
                overflow: 'hidden',
            }}
        >
            {!hasRange && (
                <>
                    <ListItem>
                        <ListItemText primary={getValueText(min)} />
                        {attrFilterIndex >= 0 && (
                            <ListItemSecondaryAction>
                                <Button
                                    onClick={() =>
                                        removeAttrFilter(attrFilterIndex)
                                    }
                                >
                                    {t(
                                        'date_histogram_facet.clear_filter',
                                        `Clear filter`
                                    )}
                                </Button>
                            </ListItemSecondaryAction>
                        )}
                    </ListItem>
                </>
            )}

            {hasRange && (
                <Box
                    sx={{
                        px: 6,
                        py: 1,
                        marginTop: `${histogramHeight}px`,
                    }}
                >
                    <Slider
                        getAriaLabel={() => 'Date range'}
                        value={value}
                        onChangeCommitted={handleChangeCommitted}
                        onChange={handleChange}
                        valueLabelFormat={getValueText}
                        step={null}
                        min={min}
                        max={max}
                        marks={marks}
                        valueLabelDisplay={'on'}
                        disableSwap
                    />
                </Box>
            )}
        </Box>
    );
}
