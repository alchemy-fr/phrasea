import React, {
    Context,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

type ClosableFunc = () => boolean;

type CloseModal = (options?: {force?: boolean; modalId?: string}) => void;

export interface ModalStackValue {
    /**
     * Opens a modal using the provided component and props
     */
    openModal: <T extends StackedModalProps, P extends T>(
        component: React.ComponentType<T>,
        props?: Omit<P, keyof StackedModalProps>,
        options?: OpenModalOptions
    ) => any;

    /**
     * Closes the active modal
     */
    closeModal: CloseModal;

    /**
     * Closes all modals
     */
    closeAllModals: () => void;

    stack: Stack;

    setCloseConstraint: (modalIndex: number, constraint: ClosableFunc) => void;

    onPopState: (e: PopStateEvent) => void;
}

type ForwardedContext<T = any> = {
    context: Context<T>;
    value: T;
};

export type OpenModalOptions = {
    /**
     * Replaces the active modal in the stack
     */
    replace?: boolean;
    forwardedContexts?: ForwardedContext[];
};

export interface StackedModalProps {
    open: boolean;
    modalIndex: number;
    modalId: string;
}

export type StackedModal = {
    id: string;
    component: React.ComponentType;
    open: boolean;
    props: any;
    closeConstraint?: ClosableFunc | undefined;
    forceClose: boolean;
    forwardedContexts?: ForwardedContext[];
};

export type Stack = {
    modals: StackedModal[];
    current: string | null;
};

const ModalStackContext = React.createContext<ModalStackValue>({} as any);

export interface ModalStackProps {
    renderBackdrop?: React.ComponentType<any>;
    renderModals?: React.ComponentType<ModalStackValue>;
    children?: React.ReactNode;
}

export default function ModalStack({
    children,
    renderModals: ModalsComponent = Modals,
    renderBackdrop: BackdropComponent,
}: ModalStackProps) {
    const [stack, setStack] = useState<Stack>({
        modals: [],
        current: null,
    });
    const idInc = useRef<number>(0);

    function pushHistory(modalId: string | null, replace?: boolean): void {
        if (replace) {
            window.history.replaceState(
                {
                    modal: modalId,
                },
                ''
            );
        } else {
            window.history.pushState(
                {
                    modal: modalId,
                },
                ''
            );
        }
    }

    const value = useMemo<ModalStackValue>(() => {
        function dismissAll() {
            setStack({
                modals: [],
                current: null,
            });
        }

        function getNewCurrentId(modals: StackedModal[]): string | null {
            let newCurrent: string | null = null;

            modals.forEach(m => {
                if (m.open) {
                    newCurrent = m.id;
                }
            });

            return newCurrent;
        }

        function isCloseable(modal: StackedModal): boolean {
            const c = modal?.closeConstraint ?? undefined;
            if (c) {
                return c();
            }

            return true;
        }

        const currentModal =
            stack.current !== null
                ? stack.modals.find(m => m.id === stack.current)
                : undefined;

        const closeCurrent: CloseModal = ({force, modalId} = {}) => {
            let targetModal = currentModal;
            if (modalId) {
                targetModal = stack.modals.find(m => m.id === modalId);
                if (!targetModal) {
                    // eslint-disable-next-line no-console
                    console.error('No modal found with id', modalId);
                    return;
                }
            }

            if (targetModal && (force || isCloseable(targetModal))) {
                targetModal.forceClose = true;
                setStack(prev => {
                    const newModals = prev.modals
                        .filter(m => m.open)
                        .map(m =>
                            m.id === targetModal.id
                                ? {
                                      ...m,
                                      open: false,
                                  }
                                : m
                        );

                    const newCurrent = getNewCurrentId(newModals);

                    pushHistory(newCurrent, true);

                    return {
                        modals: newModals,
                        current: newCurrent,
                    };
                });
            }
        };

        function setCloseConstraint(
            modalIndex: number,
            constraint: ClosableFunc | undefined
        ): void {
            if (!currentModal) {
                // Ignore component trying to update closeConstraint when modal is already hidden
                return;
            }

            stack.modals[modalIndex].closeConstraint = constraint;
        }

        const onPopState = () => {
            const l: string | null = window.history.state?.modal || null;
            if (l) {
                const modal = stack.modals.find(m => m.id === l);
                if (!modal) {
                    const newCurrent = getNewCurrentId(stack.modals);
                    pushHistory(newCurrent, true);
                    return;
                }
            }

            if (currentModal) {
                if (!currentModal.forceClose && !isCloseable(currentModal)) {
                    pushHistory(getNewCurrentId(stack.modals));
                } else {
                    setStack(prev => {
                        const newModals = prev.modals
                            .filter(m => m.open)
                            .map(m =>
                                m.id === currentModal.id
                                    ? {
                                          ...m,
                                          open: false,
                                      }
                                    : m
                            );

                        const newCurrent = getNewCurrentId(newModals);

                        pushHistory(newCurrent, true);

                        return {
                            modals: newModals,
                            current: newCurrent,
                        };
                    });
                }
            }
        };

        return {
            setCloseConstraint,
            stack,
            openModal: (component, props, options) => {
                setStack(prev => {
                    let newModals = prev.modals.filter(m => m.open);
                    if (options?.replace) {
                        newModals = newModals.slice(0, newModals.length - 1);
                    }

                    const id = `m${(idInc.current++).toString()}`;

                    pushHistory(id, options?.replace);

                    newModals.push({
                        id,
                        component,
                        props,
                        forceClose: false,
                        open: true,
                        forwardedContexts: options?.forwardedContexts,
                    } as StackedModal);

                    return {
                        modals: newModals,
                        current: id,
                    };
                });
            },
            closeModal: closeCurrent,
            closeAllModals: dismissAll,
            onPopState,
        };
    }, [stack]);

    useEffect(() => {
        window.addEventListener('popstate', value.onPopState);

        return () => {
            window.removeEventListener('popstate', value.onPopState);
        };
    }, [value]);

    return (
        <ModalStackContext.Provider value={value}>
            {children}
            {BackdropComponent && value.stack.modals.length > 0 && (
                <BackdropComponent />
            )}
            <ModalsComponent {...value} />
        </ModalStackContext.Provider>
    );
}

function Modals({stack}: ModalStackValue) {
    return (
        <>
            {stack.modals.map((modal, index) => {
                let contextStack = (
                    <modal.component
                        key={modal.id}
                        open={modal.open}
                        modalIndex={index}
                        modalId={modal.id}
                        {...modal.props}
                    />
                );

                if (modal.forwardedContexts) {
                    modal.forwardedContexts.forEach((fc, i) => {
                        const C = fc.context;
                        const prev = contextStack;

                        contextStack = (
                            <C.Provider key={i} value={fc.value}>
                                {prev}
                            </C.Provider>
                        );
                    });
                }

                return contextStack;
            })}
        </>
    );
}

export function useModals() {
    return useContext(ModalStackContext);
}
