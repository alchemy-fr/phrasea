'use client';

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import type {
    QuarantineBatchEligibility,
    QuarantineBatchPrepareItem,
} from '@/types/api';
import {prepareQuarantineBatch} from '@/lib/api/assets';

/** Batching of the selection changes before asking the server */
const debounceDelay = 200;

const eligibilityKeys: QuarantineBatchEligibility[] = [
    'bypass',
    'trash',
    'delete',
    'mergeAsVersion',
    'mergeDiscard',
];

export type QuarantineEligibilityCounts = Record<
    QuarantineBatchEligibility,
    number
>;

/**
 * Which batch action each selected asset is eligible to, as told by the
 * server. Answers are cached by asset: only the newly selected assets are
 * asked for, and unselecting one recounts locally.
 */
export function useQuarantineEligibility(ids: string[]) {
    const [cache, setCache] = useState<Map<string, QuarantineBatchPrepareItem>>(
        () => new Map()
    );
    // The request for these assets failed: not asked again until they change
    const [failedKey, setFailedKey] = useState<string>();
    // Answers to a request sent before an invalidation are stale
    const generation = useRef(0);
    const [version, setVersion] = useState(0);

    const missing = useMemo(
        () => ids.filter(id => !cache.has(id)),
        [ids, cache]
    );
    const missingKey = missing.join(',');
    const failed = !!missingKey && failedKey === missingKey;

    useEffect(() => {
        if (!missingKey || failed) {
            return;
        }
        const sentFor = generation.current;
        const timer = setTimeout(() => {
            prepareQuarantineBatch(missingKey.split(','))
                .then(items => {
                    if (sentFor !== generation.current) {
                        return;
                    }
                    setCache(prev => {
                        const next = new Map(prev);
                        items.forEach(i => next.set(i.id, i));

                        return next;
                    });
                })
                .catch(() => {
                    if (sentFor === generation.current) {
                        setFailedKey(missingKey);
                    }
                });
        }, debounceDelay);

        return () => clearTimeout(timer);
    }, [missingKey, failed, version]);

    const items = useMemo(
        () =>
            ids
                .map(id => cache.get(id))
                .filter((i): i is QuarantineBatchPrepareItem => !!i),
        [ids, cache]
    );

    const counts = useMemo(
        () =>
            Object.fromEntries(
                eligibilityKeys.map(k => [k, items.filter(i => i[k]).length])
            ) as QuarantineEligibilityCounts,
        [items]
    );

    /** To be called once the state of the assets changed (batch run, refresh) */
    const invalidate = useCallback(() => {
        generation.current++;
        setVersion(v => v + 1);
        setFailedKey(undefined);
        setCache(new Map());
    }, []);

    return {
        /** Eligibility of the selected assets known so far, by asset ID */
        items: useMemo(() => new Map(items.map(i => [i.id, i])), [items]),
        counts,
        loading: missing.length > 0 && !failed,
        failed,
        invalidate,
    };
}
