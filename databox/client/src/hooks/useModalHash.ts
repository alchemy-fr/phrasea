import {useModals} from "@mattjennings/react-modal-stack";
import React, {useCallback, useRef} from "react";
import {OpenModalOptions, StackedModalProps} from "@mattjennings/react-modal-stack/dist/ModalStack";
import useHash from "../lib/useHash";

type OpenModal = <T extends StackedModalProps, P extends T>(component: React.ComponentType<T>, props?: Omit<P, keyof StackedModalProps>, options?: OpenModalOptions) => any;

function getModalLevel(hash: string): number | null {
    const params = new URLSearchParams(hash.substring(1));
    const level = parseInt(params.get('modal') || '');
    if (!isNaN(level) && level >= 0) {
        return level;
    }

    return null;
}

export function useModalHash() {
    const {
        closeModal: _closeModal,
        openModal: _openModal,
        stack,
        closeModals,
        closeAllModals,
    } = useModals();
    const [hash, updateHash] = useHash();

    const openModal = useCallback<OpenModal>((component, props, options) => {
        const r = _openModal(component, props, options);

        const params = new URLSearchParams(hash.substring(1));
        const currentLevel = stack.length;
        params.set('modal', currentLevel.toString());
        updateHash(params.toString());

        console.log('stack', stack);

        const onHashChanged = (): void => {
            const l = getModalLevel(window.location.hash);
            console.log('onHashChanged', l, stack.length);
            if (null === l || stack.length !== l) {
                console.log('closeModal', l);
                _closeModal();
                window.removeEventListener('hashchange', onHashChanged);
            }
        };

        window.addEventListener('hashchange', onHashChanged);

        return r;
    }, [_openModal, hash, stack]);

    const closeModal = useCallback(() => {
        if (null !== getModalLevel(hash)) {
            window.history.go(-1);
        }
    }, [hash]);

    return {
        closeModal,
        openModal,
        stack,
        closeModals,
        closeAllModals,
    }
}
