import React, {useContext, useEffect, useMemo, useRef, useState} from 'react'

type ClosableFunc = () => boolean;

export interface ModalStackValue {
    /**
     * Opens a modal using the provided component and props
     */
    openModal: <T extends StackedModalProps, P extends T>(
        component: React.ComponentType<T>,
        props?: Omit<P, keyof StackedModalProps>,
        options?: OpenModalOptions
    ) => any

    /**
     * Closes the active modal
     */
    closeModal: (force?: boolean) => void

    isCloseable: () => boolean

    /**
     * Closes all modals
     */
    closeAllModals: () => void

    stack: Stack;

    setCloseConstraint: (constraint: ClosableFunc) => void;

    onPopState: (e: PopStateEvent) => void;
}

export type OpenModalOptions = {
    /**
     * Replaces the active modal in the stack
     */
    replace?: boolean
}

export interface StackedModalProps {
    open: boolean;
}

export type StackedModal = {
    id: string;
    component: React.ComponentType;
    props: any;
    closeConstraint?: ClosableFunc | undefined;
    forceClose: boolean;
}

export type Stack = {
    modals: StackedModal[];
    current: number;
}

const ModalStackContext = React.createContext<ModalStackValue>({} as any)

export interface ModalStackProps {
    renderBackdrop?: React.ComponentType<any>
    renderModals?: React.ComponentType<ModalStackValue>
    children?: React.ReactNode
}

function decreaseState(l: number, step = 1) {
    window.history.replaceState(l >= 1 ? {
        modal: l - step,
    } : {}, '');
}

export default function ModalStack({
                                       children,
                                       renderModals: ModalsComponent = Modals,
                                       renderBackdrop: BackdropComponent,
                                   }: ModalStackProps) {
    const [stack, setStack] = useState<Stack>({
        modals: [],
        current: -1,
    });
    const idInc = useRef<number>(0);

    const value = useMemo<ModalStackValue>(() => {
        function dismissAll() {
            setStack({
                modals: [],
                current: -1,
            });
        }

        function isCloseable(): boolean {
            const c = stack.modals[stack.current]?.closeConstraint ?? undefined;
            if (c) {
                return c();
            }

            return true;
        }

        const currentModal = stack.current >= 0 ? stack.modals[stack.current] : undefined;

        function closeCurrent(force = false): void {
            if (currentModal && (force || isCloseable())) {
                currentModal.forceClose = true;
                const l = window.history.state?.modal;
                if (undefined !== l) {
                    decreaseState(l);
                    setStack(prev => ({
                        modals: l < prev.modals.length - 1 ? prev.modals.slice(0, l + 2) : prev.modals,
                        current: prev.current - 1,
                    }));
                } else if (force) {
                    setStack(prev => ({
                        ...prev,
                        current: prev.current - 1,
                    }));
                }
            }
        }

        function setCloseConstraint(constraint: ClosableFunc | undefined): void {
            if (!currentModal) {
                // Ignore component trying to update closeConstraint when modal is already hidden
                return;
            }
            currentModal.closeConstraint = constraint;
        }

        const onPopState = (e: PopStateEvent) => {
            const l = window.history.state?.modal;

            if (l >= stack.modals.length) {
                decreaseState(l, 2);

                return;
            }

            if (currentModal
                && (undefined === l || stack.current >= (l + 1))
            ) {
                if (!currentModal.forceClose && !isCloseable()) {
                    window.history.pushState({
                        modal: l !== undefined ? l + 1 : 0,
                    }, '');
                } else {
                    setStack(prev => ({
                        modals: l !== undefined && l < prev.modals.length - 1 ? prev.modals.slice(0, l + 2) : prev.modals,
                        current: prev.current - 1,
                    }));
                }
            }
        }

        return {
            setCloseConstraint,
            isCloseable,
            stack,
            openModal: (component, props, options) => {
                setStack((prev) => {
                    let newModals = prev.modals.slice(0, prev.current + 1);
                    let newCurrent = newModals.length;
                    if (options?.replace) {
                        newModals = prev.modals.slice(0, prev.modals.length - 1);
                        newCurrent--;
                    } else {
                        window.history.pushState({
                            modal: newCurrent,
                        }, '');
                    }

                    newModals.push({
                        id: (idInc.current++).toString(),
                        component,
                        props,
                        forceClose: false,
                    } as StackedModal);

                    return {
                        modals: newModals,
                        current: newCurrent,
                    };
                });
            },
            closeModal: closeCurrent,
            closeAllModals: dismissAll,
            onPopState,
        }
    }, [stack]);

    useEffect(() => {
        window.addEventListener('popstate', value.onPopState);

        return () => {
            window.removeEventListener('popstate', value.onPopState);
        };
    }, [value]);

    return <ModalStackContext.Provider value={value}>
        {children}
        {BackdropComponent && value.stack.modals.length > 0 && <BackdropComponent/>}
        <ModalsComponent {...value} />
    </ModalStackContext.Provider>
}

function Modals({stack}: ModalStackValue) {
    return <>
        {stack.modals.map((modal, index) => {
            return (
                <modal.component
                    key={modal.id}
                    open={index <= stack.current}
                    {...modal.props}
                />
            )
        })}
    </>
}

export function useModals() {
    return useContext(ModalStackContext)
}
