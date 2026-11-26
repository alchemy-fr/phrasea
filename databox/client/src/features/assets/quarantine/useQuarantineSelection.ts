'use client';

import {useCallback, useMemo, useRef, useState} from 'react';
import type {Asset} from '@/types/api';
import {computeSelection} from '@/features/assets/list/SelectionProvider';

type ModifierKeys = {shiftKey?: boolean; ctrlKey?: boolean; metaKey?: boolean};

/**
 * The assets of the quarantine queue checked for a batch action, as in a file
 * explorer. A click on a checkbox, or a Ctrl/Cmd+click on a row, toggles an
 * asset; Shift+click on either adds the range from the anchor. The anchor is
 * the last row opened or toggled, or else the asset opened on the right
 * (`openedId`).
 *
 * Only the assets still listed count: those leaving the queue (resolved, or
 * gone from the server) drop out of the selection.
 */
export function useQuarantineSelection(assets: Asset[], openedId?: string) {
    const [checked, setChecked] = useState<string[]>([]);
    const anchor = useRef<string | undefined>(undefined);

    const selected = useMemo(() => {
        const ids = new Set(checked);

        return assets.filter(a => ids.has(a.id));
    }, [assets, checked]);
    const ids = useMemo(() => selected.map(a => a.id), [selected]);

    const toggle = useCallback(
        (asset: Asset, e?: ModifierKeys) => {
            const from = anchor.current ?? openedId;
            setChecked(prev => {
                const current = assets.filter(a => prev.includes(a.id));

                return computeSelection(
                    current,
                    asset,
                    [assets],
                    {ctrlKey: true, shiftKey: e?.shiftKey},
                    from
                ).map(a => a.id);
            });
            if (!e?.shiftKey) {
                anchor.current = asset.id;
            }
        },
        [assets, openedId]
    );

    /**
     * Ctrl/Cmd+click or Shift+click on a row. The first Ctrl/Cmd+click while
     * nothing is checked also checks the opened asset, which the user sees as
     * already part of the selection.
     */
    const clickItem = useCallback(
        (asset: Asset, e: ModifierKeys) => {
            if (
                (e.ctrlKey || e.metaKey) &&
                !e.shiftKey &&
                ids.length === 0 &&
                openedId &&
                openedId !== asset.id &&
                assets.some(a => a.id === openedId)
            ) {
                setChecked([openedId, asset.id]);
                anchor.current = asset.id;

                return;
            }

            toggle(asset, e);
        },
        [assets, ids, openedId, toggle]
    );

    const setAnchor = useCallback((id: string) => {
        anchor.current = id;
    }, []);

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
        clickItem,
        setAnchor,
        selectAll,
        clear,
        remove,
    };
}
