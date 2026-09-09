'use client';

import {
    ComponentType,
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
} from 'react';
import {uniqueId} from '@/lib/utils/misc';

/**
 * Props injected into every imperative modal component.
 * Imperative modals are used for transient dialogs (confirmations, forms
 * bound to a selection). Shareable screens (asset view, manage dialogs) are
 * routed instead (see app/(app)/@modal).
 */
export type ModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    modalId: string;
};

type ModalEntry = {
    id: string;
    component: ComponentType<any>;
    props: Record<string, unknown>;
    open: boolean;
};

type OpenModal = <P extends ModalProps>(
    component: ComponentType<P>,
    props: Omit<P, keyof ModalProps>
) => {id: string; close: () => void};

type ModalContextValue = {
    openModal: OpenModal;
    closeModal: (id: string) => void;
    closeAll: () => void;
    count: number;
};

const ModalContext = createContext<ModalContextValue | null>(null);

export function ModalProvider({children}: PropsWithChildren) {
    const [modals, setModals] = useState<ModalEntry[]>([]);
    const timers = useRef<Record<string, ReturnType<typeof setTimeout>>>({});

    const closeModal = useCallback((id: string) => {
        // Keep the element mounted during the exit animation
        setModals(prev =>
            prev.map(m => (m.id === id ? {...m, open: false} : m))
        );
        clearTimeout(timers.current[id]);
        timers.current[id] = setTimeout(() => {
            setModals(prev => prev.filter(m => m.id !== id));
            delete timers.current[id];
        }, 250);
    }, []);

    const openModal = useCallback<OpenModal>(
        (component, props) => {
            const id = uniqueId();
            setModals(prev => [
                ...prev,
                {
                    id,
                    component,
                    props: props as Record<string, unknown>,
                    open: true,
                },
            ]);

            return {id, close: () => closeModal(id)};
        },
        [closeModal]
    );

    const closeAll = useCallback(() => {
        setModals(prev => prev.map(m => ({...m, open: false})));
        setTimeout(() => setModals([]), 250);
    }, []);

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
