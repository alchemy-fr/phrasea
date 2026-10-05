import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, renderHook} from '@testing-library/react';
import {useSwitchUserShortcut} from './useSwitchUserShortcut';
import {SwitchUserDialog} from './SwitchUserDialog';

const {auth, openModal} = vi.hoisted(() => ({
    auth: {canImpersonate: true},
    openModal: vi.fn(),
}));

vi.mock('@/lib/auth/AuthProvider', () => ({useAuth: () => auth}));
vi.mock('@/components/modals/ModalProvider', () => ({
    useModals: () => ({openModal}),
}));
vi.mock('./SwitchUserDialog', () => ({SwitchUserDialog: () => null}));

describe('useSwitchUserShortcut', () => {
    beforeEach(() => {
        openModal.mockClear();
        auth.canImpersonate = true;
    });

    it('opens the dialog on Ctrl+U', () => {
        renderHook(() => useSwitchUserShortcut());

        const e = new KeyboardEvent('keydown', {
            key: 'u',
            ctrlKey: true,
            cancelable: true,
        });
        window.dispatchEvent(e);

        expect(e.defaultPrevented).toBe(true);
        expect(openModal).toHaveBeenCalledWith(
            SwitchUserDialog,
            {},
            {key: 'switch-user'}
        );
    });

    it('ignores other keys and rich text editors', () => {
        renderHook(() => useSwitchUserShortcut());

        fireEvent.keyDown(window, {key: 'u'});
        fireEvent.keyDown(window, {key: 'u', ctrlKey: true, shiftKey: true});
        fireEvent.keyDown(window, {key: 'i', ctrlKey: true});

        const editor = document.createElement('div');
        editor.contentEditable = 'true';
        Object.defineProperty(editor, 'isContentEditable', {value: true});
        editor.tabIndex = 0;
        document.body.appendChild(editor);
        editor.focus();
        fireEvent.keyDown(window, {key: 'u', ctrlKey: true});
        editor.remove();

        expect(openModal).not.toHaveBeenCalled();
    });

    it('is disabled for users who cannot impersonate', () => {
        auth.canImpersonate = false;
        const {result} = renderHook(() => useSwitchUserShortcut());

        fireEvent.keyDown(window, {key: 'u', ctrlKey: true});

        expect(result.current).toBeUndefined();
        expect(openModal).not.toHaveBeenCalled();
    });
});
