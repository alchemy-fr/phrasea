import React, {
    ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import {FacetGroupProps} from '../Facets';
import {Box, Button, Slider, useTheme} from '@mui/material';
import moment from 'moment';
import {SearchContext} from '../../Search/SearchContext';
import {useTranslation} from 'react-i18next';
import {
    dateToStringDate,
    dateToTimestamp,
    getDate,
} from '../../../../lib/date.ts';
import {AQLConditionBuilder} from '../../Search/AQL/AQLConditionBuilder.ts';
import {parseAQLQuery} from '../../Search/AQL/AQL.ts';
import {extractField} from './attributeUtils.ts';
import {AttributeType} from '../../../../api/types.ts';

type DateTuple = [number, number];
export default function DateHistogramFacet({facet, name}: FacetGroupProps) {
    const fieldName = extractField(name);
    const [value, setValue] = useState<DateTuple>([0, 0]);
    const [commitedValue, setCommitedValue] = React.useState<DateTuple>([0, 0]);
    const {t} = useTranslation();
    const {conditions, upsertCondition, removeCondition} =
        useContext(SearchContext)!;
    const condition = conditions.find(_f => _f.id === name);
    const theme = useTheme();
    const histogramHeight = 50;
    const displayTime = Boolean(
        facet.meta.type === AttributeType.DateTime &&
            /^\d+[hms]$/.test(facet.interval ?? '')
    );

    const buckets = useMemo(
        () =>
            facet.buckets.map(b => ({
                ...b,
                key: (b.key as number) / 1000,
            })),
        [facet.buckets]
    );

    const min = buckets[0].key as number;
    const max = buckets[buckets.length - 1].key as number;
    const step =
        buckets.length >= 2
            ? (buckets[1].key as number) - (buckets[0].key as number)
            : undefined;

    const getValueText = React.useCallback(
        (value: number): ReactNode => {
            const m = moment(getDate(value));

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

    useEffect(() => {
        if (condition) {
            const queryBuilder = AQLConditionBuilder.fromQuery(
                fieldName,
                condition ? parseAQLQuery(condition.query) : undefined
            );

            const values = queryBuilder.getValues();
            if (values.length === 2) {
                const v: DateTuple = [
                    dateToTimestamp(values[0] as number | string)!,
                    dateToTimestamp(values[1] as number | string)!,
                ];
                setValue(v);
                setCommitedValue(v);
                return;
            }
        }

        if (value[0] === 0 && value[1] === 0) {
            const v: DateTuple = [min, max];
            setValue(v);
            setCommitedValue(v);
        }
    }, [min, max, condition]);

    const handleChange = useCallback(
        (_event: Event, newValue: number | number[]) => {
            setValue(newValue as DateTuple);
        },
        [setValue]
    );

    const handleChangeCommitted = useCallback(
        (_event: React.SyntheticEvent | Event, newValue: number | number[]) => {
            const [left, right] = newValue as DateTuple;

            upsertCondition({
                id: name,
                query: `${fieldName} BETWEEN "${dateToStringDate(left)}" AND "${dateToStringDate(right)}"`,
            });
            setCommitedValue(newValue as DateTuple);
        },
        [step, name, setCommitedValue]
    );

    const colorActive = theme.palette.secondary.main;
    const greyInactive = theme.palette.grey[500];

    const marks = useMemo(() => {
        const maxCount = buckets.reduce(function (prev, curr) {
            return prev.doc_count > curr.doc_count ? prev : curr;
        }).doc_count;
        const ratio = histogramHeight / maxCount;

        const marks = buckets.map(b => ({
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

        if (marks.length === 0 || marks[0].value > commitedValue[0]) {
            marks.unshift({
                value: commitedValue[0],
                label: <div />,
            });
        }

        if (marks[marks.length - 1].value < commitedValue[1]) {
            marks.push({
                value: commitedValue[1],
                label: <div />,
            });
        }

        return marks;
    }, [buckets, colorActive, greyInactive, value, commitedValue]);

    const finalValue: DateTuple =
        value[0] === 0 && value[1] === 0 ? [min, max] : value;
    const finalCommittedValue: DateTuple =
        commitedValue[0] === 0 && commitedValue[1] === 0
            ? [min, max]
            : commitedValue;

    return (
        <Box
            style={{
                overflow: 'hidden',
            }}
        >
            <Box
                sx={{
                    px: 6,
                    py: 1,
                    marginTop: `${histogramHeight}px`,
                }}
            >
                <Slider
                    getAriaLabel={() => 'Date range'}
                    value={finalValue}
                    onChangeCommitted={handleChangeCommitted}
                    onChange={handleChange}
                    valueLabelFormat={getValueText}
                    step={null}
                    min={Math.min(finalCommittedValue[0], min)}
                    max={Math.max(max, finalCommittedValue[1])}
                    marks={marks}
                    valueLabelDisplay={'on'}
                    disableSwap
                />
                {!!condition && (
                    <Button
                        onClick={() => removeCondition(condition)}
                        size={'small'}
                    >
                        {t('date_histogram_facet.clear_filter', `Clear filter`)}
                    </Button>
                )}
            </Box>
        </Box>
    );
}
