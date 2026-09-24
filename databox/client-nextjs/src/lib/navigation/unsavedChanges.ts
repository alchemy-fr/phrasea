'use client';

import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useId,
    useState,
} from 'react';
import {create} from 'zustand';

/**
 * Registry of the forms holding unsaved changes.
 *
 * Every dirty form registers itself (`useDirtyState` /
 * `useUnsavedChangesPrompt`) under the scope it is rendered in: the root, or
 * the dialog around it. `UnsavedChangesGuard` then asks for confirmation
 * before the window closes or the app navigates away, and a dialog asks
 * before closing when its own scope is dirty.
 */

type PendingConfirm = {resolve: (discard: boolean) => void};

type UnsavedChangesState = {
    /** Dirty form id => scope id */
    dirty: Record<string, string>;
    pending: PendingConfirm | null;
};

export const useUnsavedChangesStore = create<UnsavedChangesState>(() => ({
    dirty: {},
    pending: null,
}));

/**
 * Scope the dirty forms below register in (e.g. a dialog), as a path: a
 * scope contains the scopes nested in it.
 */
export const UnsavedChangesScope = createContext<string>('');

/** New scope nested in the enclosing one, to give to `UnsavedChangesScope` */
export function useUnsavedChangesChildScope(): string {
    const parent = useContext(UnsavedChangesScope);
    const id = useId();

    return `${parent}/${id}`;
}

/** Grace period after a discard: the navigation that follows must go through */
const DISCARD_GRACE_MS = 1000;
let discardedAt = 0;

export function hasUnsavedChanges(scope?: string): boolean {
    if (Date.now() - discardedAt < DISCARD_GRACE_MS) {
        return false;
    }
    const scopes = Object.values(useUnsavedChangesStore.getState().dirty);

    return scope === undefined
        ? scopes.length > 0
        : scopes.some(s => s === scope || s.startsWith(`${scope}/`));
}

/**
 * Asks the user whether to drop the unsaved changes. Resolves `true` when
 * they accept (or when there is nothing to lose, see `confirmLeave`).
 */
export function askDiscardChanges(): Promise<boolean> {
    const {pending} = useUnsavedChangesStore.getState();
    if (pending) {
        // A single question at a time: the previous one is dismissed
        pending.resolve(false);
    }

    return new Promise(resolve => {
        useUnsavedChangesStore.setState({
            pending: {
                resolve: discard => {
                    if (discard) {
                        discardedAt = Date.now();
                    }
                    useUnsavedChangesStore.setState({pending: null});
                    resolve(discard);
                },
            },
        });
    });
}

/**
 * Resolves `true` right away when no form (of `scope`, or anywhere) holds
 * unsaved changes, otherwise asks the user.
 */
export async function confirmLeave(scope?: string): Promise<boolean> {
    return hasUnsavedChanges(scope) ? askDiscardChanges() : true;
}

/**
 * Flags the enclosing form as holding unsaved changes while `dirty`: leaving
 * (closing the window, navigating, closing the dialog) then asks first.
 */
export function useUnsavedChangesPrompt(dirty: boolean): void {
    const id = useId();
    const scope = useContext(UnsavedChangesScope);

    useEffect(() => {
        if (!dirty) {
            return;
        }
        useUnsavedChangesStore.setState(s => ({
            dirty: {...s.dirty, [id]: scope},
        }));

        return () => {
            useUnsavedChangesStore.setState(s => {
                const {[id]: _removed, ...rest} = s.dirty;

                return {dirty: rest};
            });
        };
    }, [dirty, id, scope]);
}

/**
 * Tracks whether `values` differ from what they were when the form mounted
 * (or last saved) and guards them with `useUnsavedChangesPrompt`.
 *
 * Call `markSaved()` once saved, with the form staying on screen. Pass
 * `ready: false` while the initial values load: the snapshot is taken when it
 * turns true.
 */
export function useDirtyState(
    values: unknown,
    {ready = true}: {ready?: boolean} = {}
): {dirty: boolean; markSaved: () => void} {
    const serialized = serialize(values);
    const [initial, setInitial] = useState<string | undefined>(
        ready ? serialized : undefined
    );
    if (ready && initial === undefined) {
        setInitial(serialized);
    }
    const dirty = ready && initial !== undefined && serialized !== initial;
    useUnsavedChangesPrompt(dirty);

    const markSaved = useCallback(() => setInitial(serialized), [serialized]);

    return {dirty, markSaved};
}

/** Order-insensitive on object keys, so that `{a, b}` equals `{b, a}` */
function serialize(value: unknown): string {
    return JSON.stringify(value, (_key, v) =>
        v && typeof v === 'object' && !Array.isArray(v)
            ? Object.fromEntries(
                  Object.entries(v)
                      // `undefined`, `''` and absent are the same thing for a
                      // form (e.g. a translation typed then erased)
                      .filter(([, x]) => x !== undefined && x !== '')
                      .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))
              )
            : v
    );
}
