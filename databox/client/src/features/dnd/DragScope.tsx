'use client';

import {
    createContext,
    PropsWithChildren,
    useContext,
    useId,
    useMemo,
} from 'react';

export type DragScope = {
    /** Identifies the list, so that two lists mounted at once never share draggable ids */
    scope: string;
    /** The basket the list displays, if any */
    basketId?: string;
};

const DragScopeContext = createContext<DragScope>({scope: 'default'});

export function DragScopeProvider({
    basketId,
    children,
}: PropsWithChildren<{basketId?: string}>) {
    const scope = useId();
    const value = useMemo(() => ({scope, basketId}), [scope, basketId]);

    return (
        <DragScopeContext.Provider value={value}>
            {children}
        </DragScopeContext.Provider>
    );
}

export function useDragScope(): DragScope {
    return useContext(DragScopeContext);
}
