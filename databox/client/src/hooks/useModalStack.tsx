import React, {useContext, useMemo, useRef, useState} from 'react'
import useHash from "../lib/useHash";

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
}

export type OpenModalOptions = {
    /**
     * Replaces the active modal in the stack
     */
    replace?: boolean
}

export interface StackedModalProps {
    open?: boolean;
}

export type StackedModal = {
    id: string;
    component: React.ComponentType;
    props: any;
    closeConstraint?: ClosableFunc | undefined;
    opening: boolean;
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

function getModalLevel(hash: string): number | null {
    const params = new URLSearchParams(hash.substring(1));
    const level = parseInt(params.get('modal') || '');
    if (!isNaN(level) && level >= 0) {
        return level;
    }

    return null;
}

function changeHash(updateHash: (newHash: string) => boolean, hash: string, level: number): void {
    const params = new URLSearchParams(hash.substring(1));
    params.set('modal', level.toString());
    updateHash(params.toString());
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
    const [hash, updateHash] = useHash();
    const lastHash = useRef<string>();
    const idInc = useRef<number>(0);
    const hashChanged = lastHash.current !== hash;
    lastHash.current = hash;

    const value = useMemo<ModalStackValue>(() => {
        // TODO close previous modals after 1s
        function pop(amount = 1) {
            return setStack((prev) => {
                return {
                    modals: prev.modals.slice(0, prev.modals.length - amount),
                    current: Math.min(prev.modals.length - 1 - amount, prev.current),
                };
            });
        }

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
                const modalLevel = getModalLevel(hash);
                if (null !== modalLevel) {
                    window.history.go(-1);
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

        if (hashChanged) {
            const l = getModalLevel(hash);

            if (currentModal
                && (null === l || stack.current >= (l + 1))
            ) {
                if (!currentModal.opening) {
                    if (!currentModal.forceClose && !isCloseable()) {
                        setTimeout(() => {
                            changeHash(updateHash, hash, stack.current);
                        }, 0);
                    } else {
                        setStack(prev => ({
                            modals: l !== null && l < prev.modals.length - 1 ? prev.modals.slice(0, l + 2) : prev.modals,
                            current: prev.current - 1,
                        }));
                    }
                }
            }

            if (currentModal && stack.current === l) {
                // Mark opened lower modals that were not updated because of to fast hash changes:
                stack.modals.forEach((m, index) => {
                    if (index <= stack.current) {
                        m.opening = false;
                    }
                });
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
                        changeHash(updateHash, hash, newCurrent);
                    }

                    newModals.push({
                        id: (idInc.current++).toString(),
                        component,
                        props,
                        opening: true,
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
        }
    }, [stack, hash]);

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
