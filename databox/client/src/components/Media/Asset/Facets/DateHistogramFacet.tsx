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

type NumberTuple = [number, number];
export default function DateHistogramFacet({facet, name}: FacetGroupProps) {
    const {t} = useTranslation();
    const {conditions, updateCondition, removeCondition} =
        useContext(SearchContext)!;
    const condition = conditions.find(_f => _f.id === name);
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

    const [value, setValue] = useState<NumberTuple>([min, max]);

    useEffect(() => {
        if (condition) {
            setValue(condition.query.replace(/^.+ BETWEEN\s+/, '').split(' AND ').map(parseInt) as NumberTuple);
        } else {
            setValue([min, max]);
        }
    }, [min, max, condition]);

    const handleChange = useCallback(
        (_event: Event, newValue: number | number[]) => {
            setValue(newValue as NumberTuple);
        },
        [setValue]
    );

    const handleChangeCommitted = useCallback(
        (_event: React.SyntheticEvent | Event, newValue: number | number[]) => {
            if (step) {
                (newValue as NumberTuple)[1] += step;
            }

            console.log('ok', {
                id: name,
                query: `${name} BETWEEN ${(newValue as NumberTuple)[0]} AND ${(newValue as NumberTuple)[1]}`,
            });

            updateCondition({
                id: name,
                query: `${name} BETWEEN ${(newValue as NumberTuple)[0]} AND ${(newValue as NumberTuple)[1]}`,
            });
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
                        {!!condition && (
                            <ListItemSecondaryAction>
                                <Button
                                    onClick={() =>
                                        removeCondition(condition)
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
