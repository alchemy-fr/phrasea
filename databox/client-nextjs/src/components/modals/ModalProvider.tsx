'use client';

import {
    ComponentType,
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {usePathname} from 'next/navigation';
import {uniqueId} from '@/lib/utils/misc';

/** Exit animation duration of a dialog. Must match `duration-200` in dialog.tsx. */
export const modalExitDuration = 200;

/**
 * Props injected into every imperative modal component.
 * Imperative modals are used for transient dialogs (confirmations, forms
 * bound to a selection). Shareable screens (asset view, manage dialogs) are
 * routed instead (see app/(app)/@modal).
 */
export type ModalProps<R = unknown> = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /**
     * Closes the modal and resolves the promise returned by `openModal()`
     * with `result`. Dismissing the modal any other way resolves `undefined`.
     *
     * Optional: dialogs are also rendered directly as JSX in a few screens,
     * where there is no promise to settle.
     */
    resolve?: (result: R) => void;
    modalId?: string;
};

/**
 * Awaitable handle over an open modal. `await` it to get the result the modal
 * resolved with, or `undefined` when the user dismissed it.
 */
export type ModalHandle<R> = Promise<R | undefined> & {
    id: string;
    close: () => void;
};

export type OpenModalOptions = {
    /**
     * Identity of the modal. Opening it again under the same key updates the
     * live instance instead of stacking a duplicate — which is what a
     * double-click on the trigger used to do.
     */
    key?: string;
    /**
     * Keep the modal open across route changes. Off by default: a modal
     * belongs to the screen it was opened from.
     */
    keepOnNavigate?: boolean;
};

type ModalEntry = {
    id: string;
    component: ComponentType<any>;
    props: Record<string, unknown>;
    open: boolean;
};

type ModalMeta = {
    key?: string;
    keepOnNavigate: boolean;
    resolve: (result: unknown) => void;
    handle: ModalHandle<any>;
};

type ResultOf<P> = P extends ModalProps<infer R> ? R : unknown;

type OpenModal = <P extends ModalProps<any>>(
    component: ComponentType<P>,
    props: Omit<P, keyof ModalProps<any>>,
    options?: OpenModalOptions
) => ModalHandle<ResultOf<P>>;

type ModalContextValue = {
    openModal: OpenModal;
    closeModal: (id: string) => void;
    closeAll: () => void;
    count: number;
};

const ModalContext = createContext<ModalContextValue | null>(null);

export function ModalProvider({children}: PropsWithChildren) {
    const [modals, setModals] = useState<ModalEntry[]>([]);
    const timers = useRef(new Map<string, ReturnType<typeof setTimeout>>());
    const meta = useRef(new Map<string, ModalMeta>());
    const byKey = useRef(new Map<string, string>());

    useEffect(
        () => () => {
            timers.current.forEach(clearTimeout);
            timers.current.clear();
            meta.current.forEach(m => m.resolve(undefined));
            meta.current.clear();
            byKey.current.clear();
        },
        []
    );

    /**
     * Resolves the caller's promise right away, then keeps the element mounted
     * for the exit animation.
     */
    const finalize = useCallback((id: string, result: unknown) => {
        const entry = meta.current.get(id);
        if (!entry) {
            return;
        }
        meta.current.delete(id);
        if (entry.key) {
            byKey.current.delete(entry.key);
        }
        entry.resolve(result);

        setModals(prev =>
            prev.map(m => (m.id === id ? {...m, open: false} : m))
        );

        clearTimeout(timers.current.get(id));
        timers.current.set(
            id,
            setTimeout(() => {
                timers.current.delete(id);
                setModals(prev => prev.filter(m => m.id !== id));
            }, modalExitDuration)
        );
    }, []);

    const closeModal = useCallback(
        (id: string) => finalize(id, undefined),
        [finalize]
    );

    const closeAll = useCallback(() => {
        [...meta.current.keys()].forEach(id => finalize(id, undefined));
    }, [finalize]);

    const openModal = useCallback<OpenModal>(
        (component, props, options) => {
            const key = options?.key;
            const live = key ? byKey.current.get(key) : undefined;
            if (live) {
                // Already open: refresh its props instead of stacking a copy
                setModals(prev =>
                    prev.map(m =>
                        m.id === live
                            ? {...m, props: props as Record<string, unknown>}
                            : m
                    )
                );

                return meta.current.get(live)!.handle;
            }

            const id = uniqueId();
            let resolve!: (result: unknown) => void;
            const handle = Object.assign(
                new Promise<unknown>(r => {
                    resolve = r;
                }),
                {id, close: () => finalize(id, undefined)}
            ) as ModalHandle<any>;

            meta.current.set(id, {
                key,
                keepOnNavigate: options?.keepOnNavigate ?? false,
                resolve,
                handle,
            });
            if (key) {
                byKey.current.set(key, id);
            }
            setModals(prev => [
                ...prev,
                {
                    id,
                    component,
                    props: props as Record<string, unknown>,
                    open: true,
                },
            ]);

            return handle;
        },
        [finalize]
    );

    // A modal belongs to the screen it was opened from: leaving closes it.
    const pathname = usePathname();
    const lastPathname = useRef(pathname);
    useEffect(() => {
        if (lastPathname.current === pathname) {
            return;
        }
        lastPathname.current = pathname;
        [...meta.current.entries()]
            .filter(([, m]) => !m.keepOnNavigate)
            .forEach(([id]) => finalize(id, undefined));
    }, [pathname, finalize]);

    const value = useMemo(
        () => ({openModal, closeModal, closeAll, count: modals.length}),
        [openModal, closeModal, closeAll, modals.length]
    );

    return (
        <ModalContext.Provider value={value}>
            {children}
            {modals.map(({id, component: Component, props, open}) => (
                <Component
                    key={id}
                    {...props}
                    modalId={id}
                    open={open}
                    onOpenChange={(o: boolean) => {
                        if (!o) {
                            closeModal(id);
                        }
                    }}
                    resolve={(result: unknown) => finalize(id, result)}
                />
            ))}
        </ModalContext.Provider>
    );
}

export function useModals(): ModalContextValue {
    const ctx = useContext(ModalContext);
    if (!ctx) {
        throw new Error('useModals must be used within ModalProvider');
    }

    return ctx;
}
