import {useCallback, useEffect} from 'react';
import {useModals} from '@/components/modals/ModalProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {SwitchUserDialog} from './SwitchUserDialog';

export const SWITCH_USER_SHORTCUT = 'Ctrl+U';

/**
 * Opens the "switch user" dialog, from the user menu or with Ctrl/Cmd+U
 * (which overrides the browser's "view source"). Left to rich text editors,
 * where it underlines.
 */
export function useSwitchUserShortcut(): (() => void) | undefined {
    const {canImpersonate} = useAuth();
    const {openModal} = useModals();

    const open = useCallback(() => {
        openModal(SwitchUserDialog, {}, {key: 'switch-user'});
    }, [openModal]);

    useEffect(() => {
        if (!canImpersonate) {
            return;
        }
        const onKeyDown = (e: KeyboardEvent) => {
            if (
                !(e.ctrlKey || e.metaKey) ||
                e.altKey ||
                e.shiftKey ||
                e.key.toLowerCase() !== 'u'
            ) {
                return;
            }
            if (
                (document.activeElement as HTMLElement | null)
                    ?.isContentEditable
            ) {
                return;
            }
            e.preventDefault();
            open();
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [canImpersonate, open]);

    return canImpersonate ? open : undefined;
}
