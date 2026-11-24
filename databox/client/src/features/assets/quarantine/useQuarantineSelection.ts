'use client';

import {useCallback, useMemo, useRef, useState} from 'react';
import type {Asset} from '@/types/api';
import {computeSelection} from '@/features/assets/list/SelectionProvider';

/**
 * The assets of the quarantine queue checked for a batch action. A click
 * toggles an asset, Shift+click adds the range from the last one clicked.
 *
 * Only the assets still listed count: those leaving the queue (resolved, or
 * gone from the server) drop out of the selection.
 */
export function useQuarantineSelection(assets: Asset[]) {
    const [checked, setChecked] = useState<string[]>([]);
    const anchor = useRef<string | undefined>(undefined);

    const selected = useMemo(() => {
        const ids = new Set(checked);

        return assets.filter(a => ids.has(a.id));
    }, [assets, checked]);
    const ids = useMemo(() => selected.map(a => a.id), [selected]);

    const toggle = useCallback(
        (asset: Asset, e?: {shiftKey?: boolean}) => {
            setChecked(prev => {
                const current = assets.filter(a => prev.includes(a.id));

                return computeSelection(
                    current,
                    asset,
                    [assets],
                    {ctrlKey: true, shiftKey: e?.shiftKey},
                    anchor.current
                ).map(a => a.id);
            });
            if (!e?.shiftKey) {
                anchor.current = asset.id;
            }
        },
        [assets]
    );

    const selectAll = useCallback(
        () => setChecked(assets.map(a => a.id)),
        [assets]
    );
    const clear = useCallback(() => setChecked([]), []);
    const remove = useCallback(
        (removed: string[]) =>
            setChecked(prev => prev.filter(id => !removed.includes(id))),
        []
    );

    return {
        /** Selected assets, in the order of the queue */
        selected,
        ids,
        isSelected: (id: string) => ids.includes(id),
        allSelected: assets.length > 0 && ids.length === assets.length,
        toggle,
        selectAll,
        clear,
        remove,
    };
}
