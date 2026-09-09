'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {AttributeType} from '@/types/api';
import type {FacetWidgetProps} from '../FacetsPanel';
import {useFacetCondition} from '../useFacetCondition';
import {Slider} from '@/components/ui/controls';
import {Button} from '@/components/ui/button';
import {formatDateTime} from '@/lib/utils/format';
import {quoteAQL} from '../../searchState';
import {parseAQL} from '../../aql/parser';
import {isCondition, isLiteral} from '../../aql/types';

/**
 * Date histogram rendered as bars with a dual-thumb range slider. Applying the
 * range produces `field BETWEEN "from" AND "to"`.
 */
export function DateHistogramFacet({name, facet}: FacetWidgetProps) {
    const {t, i18n} = useTranslation();
    const {condition, field, setRawQuery, clear} = useFacetCondition(name);

    const buckets = useMemo(
        () =>
            facet.buckets
                .map(b => ({
                    ts: Math.floor(
                        Number(
                            typeof b.key === 'object'
                                ? (b.key as any).value
                                : b.key
                        ) / 1000
                    ),
                    count: b.doc_count,
                }))
                .filter(b => !Number.isNaN(b.ts))
                .sort((a, b) => a.ts - b.ts),
        [facet.buckets]
    );
    const withTime =
        facet.meta.type === AttributeType.DateTime &&
        /^\d+[hms]$/.test(facet.interval ?? '');
    const min = buckets[0]?.ts ?? 0;
    const step = buckets.length >= 2 ? buckets[1].ts - buckets[0].ts : 86400;
    const max = (buckets[buckets.length - 1]?.ts ?? min) + step;
    const maxCount = Math.max(1, ...buckets.map(b => b.count));

    const committed = useMemo<[number, number] | undefined>(() => {
        if (!condition || condition.disabled) {
            return undefined;
        }
        const ast = parseAQL(condition.query);
        const expr = ast?.expression;
        if (expr && isCondition(expr) && Array.isArray(expr.rightOperand)) {
            const [a, b] = expr.rightOperand;
            const toTs = (v: unknown) =>
                isLiteral(v)
                    ? Math.floor(new Date(v.literal).getTime() / 1000)
                    : typeof v === 'number'
                      ? v
                      : NaN;
            const from = toTs(a);
            const to = toTs(b);
            if (!Number.isNaN(from) && !Number.isNaN(to)) {
                return [from, to];
            }
        }

        return undefined;
    }, [condition]);

    const [range, setRange] = useState<[number, number]>(
        committed ?? [min, max]
    );
    useEffect(() => {
        setRange(committed ?? [min, max]);
    }, [committed, min, max]);

    const label = (ts: number) =>
        formatDateTime(
            ts,
            withTime ? 'short' : 'medium',
            i18n.language,
            withTime
        );

    const apply = (r: [number, number]) => {
        setRawQuery(
            `${field} BETWEEN ${quoteAQL(new Date(r[0] * 1000).toISOString())} AND ${quoteAQL(new Date(r[1] * 1000).toISOString())}`
        );
    };

    if (buckets.length === 0) {
        return null;
    }

    return (
        <div className="px-1 pt-1">
            <div className="relative h-12">
                <div className="absolute inset-x-2 bottom-3 flex h-9 items-end gap-px">
                    {buckets.map(b => {
                        const inRange = b.ts >= range[0] && b.ts < range[1];

                        return (
                            <div
                                key={b.ts}
                                className={
                                    inRange
                                        ? 'flex-1 rounded-t-sm bg-primary/70'
                                        : 'flex-1 rounded-t-sm bg-muted-foreground/25'
                                }
                                style={{
                                    height: `${Math.max(4, (b.count / maxCount) * 100)}%`,
                                }}
                                title={`${label(b.ts)}: ${b.count}`}
                            />
                        );
                    })}
                </div>
                <Slider
                    className="absolute inset-x-0 bottom-0"
                    min={min}
                    max={max}
                    step={step}
                    value={range}
                    onValueChange={v =>
                        setRange([v[0], v[1]] as [number, number])
                    }
                    onValueCommit={v => apply([v[0], v[1]] as [number, number])}
                />
            </div>
            <div className="mt-2 flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                <span>{label(range[0])}</span>
                <span>{label(range[1])}</span>
            </div>
            {committed ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="mt-1 h-7 w-full text-xs"
                    onClick={clear}
                >
                    {t('facets.clear_filter', 'Clear filter')}
                </Button>
            ) : null}
        </div>
    );
}
