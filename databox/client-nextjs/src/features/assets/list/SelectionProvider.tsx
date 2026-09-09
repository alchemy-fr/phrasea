'use client';

import {
    createContext,
    MouseEvent,
    PropsWithChildren,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {Asset} from '@/types/api';

export type SelectionContextValue = {
    selection: Asset[];
    selectedIds: Set<string>;
    setSelection: (assets: Asset[]) => void;
    clear: () => void;
    toggle: (asset: Asset) => void;
    /** Handles plain / ctrl / shift click semantics against the current pages */
    onItemClick: (asset: Asset, pages: Asset[][], e?: MouseEvent) => void;
    selectAll: (pages: Asset[][]) => void;
    isSelected: (id: string) => boolean;
    disabledIds?: Set<string>;
};

const SelectionContext = createContext<SelectionContextValue | null>(null);

/**
 * Computes the new selection for a click, supporting Ctrl/Cmd (toggle) and
 * Shift (range across pages).
 */
export function computeSelection(
    current: Asset[],
    item: Asset,
    pages: Asset[][],
    e?: {ctrlKey?: boolean; metaKey?: boolean; shiftKey?: boolean}
): Asset[] {
    if (e?.ctrlKey || e?.metaKey) {
        return current.some(a => a.id === item.id)
            ? current.filter(a => a.id !== item.id)
            : [...current, item];
    }
    if (e?.shiftKey && current.length > 0) {
        const flat = pages.flat();
        const selectedIndexes = current
            .map(a => flat.findIndex(f => f.id === a.id))
            .filter(i => i >= 0);
        const itemIndex = flat.findIndex(f => f.id === item.id);
        if (itemIndex === -1 || selectedIndexes.length === 0) {
            return [item];
        }
        const start = Math.min(itemIndex, ...selectedIndexes);
        const end = Math.max(itemIndex, ...selectedIndexes);

        return flat.slice(start, end + 1);
    }

    return [item];
}

export function SelectionProvider({
    children,
    disabledIds,
    onChange,
}: PropsWithChildren<{
    disabledIds?: Set<string>;
    onChange?: (selection: Asset[]) => void;
}>) {
    const [selection, setSelectionState] = useState<Asset[]>([]);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    const setSelection = useCallback(
        (assets: Asset[]) => {
            const filtered = disabledIds
                ? assets.filter(a => !disabledIds.has(a.id))
                : assets;
            setSelectionState(filtered);
            onChangeRef.current?.(filtered);
        },
        [disabledIds]
    );

    const value = useMemo<SelectionContextValue>(() => {
        const selectedIds = new Set(selection.map(a => a.id));

        return {
            selection,
            selectedIds,
            setSelection,
            clear: () => setSelection([]),
            toggle: asset =>
                setSelection(
                    selectedIds.has(asset.id)
                        ? selection.filter(a => a.id !== asset.id)
                        : [...selection, asset]
                ),
            onItemClick: (asset, pages, e) =>
                setSelection(computeSelection(selection, asset, pages, e)),
            selectAll: pages => setSelection(pages.flat()),
            isSelected: id => selectedIds.has(id),
            disabledIds,
        };
    }, [selection, setSelection, disabledIds]);

    return (
        <SelectionContext.Provider value={value}>
            {children}
        </SelectionContext.Provider>
    );
}

export function useSelection(): SelectionContextValue {
    const ctx = useContext(SelectionContext);
    if (!ctx) {
        throw new Error('useSelection must be used within SelectionProvider');
    }

    return ctx;
}

export function useOptionalSelection(): SelectionContextValue | null {
    return useContext(SelectionContext);
}
