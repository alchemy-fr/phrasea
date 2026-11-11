'use client';

import {useId, useLayoutEffect} from 'react';
import {create} from 'zustand';

type State = {
    /** The mounted viewers, the last mounted last */
    viewers: string[];
    mount: (id: string) => void;
    unmount: (id: string) => void;
};

export const useViewerStackStore = create<State>(set => ({
    viewers: [],
    mount: id =>
        set(s => ({viewers: [...s.viewers.filter(v => v !== id), id]})),
    unmount: id => set(s => ({viewers: s.viewers.filter(v => v !== id)})),
}));

/**
 * Whether this asset viewer is the one shown. A viewer loaded with the page
 * stays mounted under the one a soft navigation opens in the `@modal` slot:
 * only the last mounted is shown and handles the keyboard, the others keep
 * their state until they are back on top.
 */
export function useActiveViewer(): boolean {
    const id = useId();
    const mount = useViewerStackStore(s => s.mount);
    const unmount = useViewerStackStore(s => s.unmount);

    useLayoutEffect(() => {
        mount(id);

        return () => unmount(id);
    }, [id, mount, unmount]);

    return useViewerStackStore(
        s => s.viewers.length === 0 || s.viewers[s.viewers.length - 1] === id
    );
}
