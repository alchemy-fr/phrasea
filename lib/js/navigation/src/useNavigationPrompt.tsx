import React, {useContext} from 'react'
import {
    UNSAFE_NavigationContext as NavigationContext,
    unstable_useBlocker as useBlocker,
    useBeforeUnload
} from 'react-router-dom';
import {useModals} from "./useModalStack";
import {TFunction} from "i18next";
import {BlockerFunction} from "@remix-run/router";

function useInRouterNavigationPrompt(message: string, when: boolean, modalIndex?: number) {
    const modalContext = useModals();
    const navContext = useContext(NavigationContext);

    const blocker = useBlocker(
        React.useMemo<boolean | BlockerFunction>(() => {
            if (when && navContext) {
                return (() => !window.confirm(message)) as BlockerFunction;
            }

            return false;
        }, [message, when, navContext])
    );

    React.useEffect(() => {
        if (!navContext && modalContext && modalIndex !== undefined) {
            modalContext.setCloseConstraint(modalIndex, () => when ? window.confirm(message) : true);
        }
    }, [blocker, modalContext, modalIndex, when]);

    useBeforeUnloadWhen(when, message);
}

function useOutsideRouterNavigationPrompt(message: string, when: boolean, modalIndex?: number) {
    const modalContext = useModals();

    React.useEffect(() => {
        if (modalContext && modalIndex !== undefined) {
            modalContext.setCloseConstraint(modalIndex, () => when ? window.confirm(message) : true);
        }
    }, [modalContext, modalIndex, when]);

    useBeforeUnloadWhen(when, message);
}

function useBeforeUnloadWhen(when: boolean, message: string): void {
    useBeforeUnload(
        React.useCallback(
            (event) => {
                if (when) {
                    event.preventDefault();
                    event.returnValue = message;
                }
            },
            [message, when]
        ),
        {capture: true}
    );
}

export function useInRouterDirtyFormPrompt(t: TFunction, isDirty: boolean, modalIndex?: number) {
    useInRouterNavigationPrompt(t('lib.navigation.dismiss_changes', 'Are you sure you want to dismiss unsaved changes?'), isDirty, modalIndex);
}

export function useOutsideRouterDirtyFormPrompt(t: TFunction, isDirty: boolean, modalIndex?: number) {
    useOutsideRouterNavigationPrompt(t('lib.navigation.dismiss_changes', 'Are you sure you want to dismiss unsaved changes?'), isDirty, modalIndex);
}
