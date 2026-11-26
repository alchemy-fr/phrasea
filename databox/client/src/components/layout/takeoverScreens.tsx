'use client';

import {
    createContext,
    PropsWithChildren,
    useContext,
    useId,
    useLayoutEffect,
} from 'react';
import {create} from 'zustand';

type State = {
    /** The mounted takeover screens, the last mounted last */
    screens: string[];
    mount: (id: string) => void;
    unmount: (id: string) => void;
};

export const useTakeoverScreenStore = create<State>(set => ({
    screens: [],
    mount: id =>
        set(s => ({screens: [...s.screens.filter(v => v !== id), id]})),
    unmount: id => set(s => ({screens: s.screens.filter(v => v !== id)})),
}));

/** The takeover screen a component is rendered in, `null` for the base page. */
const TakeoverScreenContext = createContext<string | null>(null);

/**
 * A takeover screen covers the whole window below the top bar (laid out with
 * `belowTopBar`, see `chrome.ts`) while the page under it stays mounted. What
 * the covered page renders in `fixed` position (an asset preview…) is not
 * clipped by the screen above and would show over the top bar: it hides with
 * `useCoveredByTakeoverScreen()`.
 */
export function TakeoverScreen({children}: PropsWithChildren) {
    const id = useId();
    const mount = useTakeoverScreenStore(s => s.mount);
    const unmount = useTakeoverScreenStore(s => s.unmount);

    useLayoutEffect(() => {
        mount(id);

        return () => unmount(id);
    }, [id, mount, unmount]);

    return (
        <TakeoverScreenContext.Provider value={id}>
            {children}
        </TakeoverScreenContext.Provider>
    );
}

/**
 * Whether a takeover screen was mounted over the one this component is
 * rendered in (or over the base page).
 */
export function useCoveredByTakeoverScreen(): boolean {
    const id = useContext(TakeoverScreenContext);

    return useTakeoverScreenStore(s => {
        if (id === null) {
            return s.screens.length > 0;
        }
        const index = s.screens.indexOf(id);

        return index !== -1 && index < s.screens.length - 1;
    });
}
