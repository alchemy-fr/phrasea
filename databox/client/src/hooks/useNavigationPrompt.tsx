import {ContextType, useCallback, useContext, useEffect} from 'react';
import {History, Transition} from 'history';
import {
    Navigator as BaseNavigator,
    UNSAFE_NavigationContext as NavigationContext,
} from 'react-router-dom';
import {useModals} from './useModalStack';

interface Navigator extends BaseNavigator {
    block: History['block'];
}

type NavigationContextWithBlock = ContextType<typeof NavigationContext> & {
    navigator: Navigator;
};

export function useNavigationPrompt(message: string, when: boolean): void {
    const modalContext = useModals();
    const navContext = useContext(
        NavigationContext
    ) as NavigationContextWithBlock;

    const blocker = useCallback(
        (tx: Transition) => {
            if (window.confirm(message)) {
                tx.retry();
            }
        },
        [message]
    );

    useEffect(() => {
        let unblock: any | undefined = undefined;

        if (navContext) {
            if (!when) {
                return;
            }

            unblock = navContext.navigator.block((tx: Transition) => {
                const autoUnblockingTx = {
                    ...tx,
                    retry() {
                        unblock();
                        tx.retry();
                    },
                };

                blocker(autoUnblockingTx);
            });
        } else if (modalContext) {
            modalContext.setCloseConstraint(() =>
                when ? window.confirm(message) : true
            );
        }

        return unblock;
    }, [navContext?.navigator, message, when, modalContext]);
}
