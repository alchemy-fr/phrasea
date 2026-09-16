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
    useEffect,
    useSyncExternalStore,
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
 * Selection API for list items, whose identity never changes: an item reading
 * `SelectionContext` would re-render on every click anywhere in the list — all
 * of them, for every selection change. Items subscribe to their own selected
 * state with `useIsAssetSelected` instead.
 */
export type SelectionActions = {
    subscribe: (listener: () => void) => () => void;
    isSelected: (id: string) => boolean;
    toggle: (asset: Asset) => void;
    onItemClick: (asset: Asset, pages: Asset[][], e?: MouseEvent) => void;
    selectAll: (pages: Asset[][]) => void;
    clear: () => void;
    disabledIds?: Set<string>;
};

const SelectionActionsContext = createContext<SelectionActions | null>(null);

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

    // Read by the stable actions below, so that they never change identity
    const selectionRef = useRef(selection);
    const selectedIdsRef = useRef<Set<string>>(new Set());
    const listeners = useRef(new Set<() => void>());

    const setSelection = useCallback(
        (assets: Asset[]) => {
            const filtered = disabledIds
                ? assets.filter(a => !disabledIds.has(a.id))
                : assets;
            selectionRef.current = filtered;
            selectedIdsRef.current = new Set(filtered.map(a => a.id));
            setSelectionState(filtered);
            // Item subscribers are notified right away: their re-render is
            // batched with ours in a single pass, instead of a second one
            // triggered from an effect after the first commit
            listeners.current.forEach(listener => listener());
            onChangeRef.current?.(filtered);
        },
        [disabledIds]
    );
    const setSelectionRef = useRef(setSelection);
    setSelectionRef.current = setSelection;

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

    const actions = useMemo<SelectionActions>(
        () => ({
            subscribe: listener => {
                listeners.current.add(listener);

                return () => listeners.current.delete(listener);
            },
            isSelected: id => selectedIdsRef.current.has(id),
            toggle: asset => {
                const current = selectionRef.current;
                setSelection(
                    current.some(a => a.id === asset.id)
                        ? current.filter(a => a.id !== asset.id)
                        : [...current, asset]
                );
            },
            onItemClick: (asset, pages, e) =>
                setSelection(
                    computeSelection(selectionRef.current, asset, pages, e)
                ),
            selectAll: pages => setSelection(pages.flat()),
            clear: () => setSelection([]),
            disabledIds,
        }),
        [setSelection, disabledIds]
    );

    // Escape clears the selection (outside inputs and open dialogs)
    useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key !== 'Escape' || e.defaultPrevented) {
                return;
            }
            const el = document.activeElement as HTMLElement | null;
            if (
                el &&
                (el.isContentEditable ||
                    ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName))
            ) {
                return;
            }
            if (document.querySelector('[role=dialog][data-state=open]')) {
                return;
            }
            if (selectionRef.current.length > 0) {
                setSelectionRef.current([]);
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <SelectionActionsContext.Provider value={actions}>
            <SelectionContext.Provider value={value}>
                {children}
            </SelectionContext.Provider>
        </SelectionActionsContext.Provider>
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

export function useSelectionActions(): SelectionActions {
    const ctx = useContext(SelectionActionsContext);
    if (!ctx) {
        throw new Error(
            'useSelectionActions must be used within SelectionProvider'
        );
    }

    return ctx;
}

/** Re-renders only when this asset gets selected or deselected. */
export function useIsAssetSelected(id: string): boolean {
    const {subscribe, isSelected} = useSelectionActions();

    return useSyncExternalStore(
        subscribe,
        () => isSelected(id),
        () => false
    );
}
