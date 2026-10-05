import {describe, expect, it, vi} from 'vitest';
import {renderHook} from '@testing-library/react';
import type {PropsWithChildren} from 'react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {useAssetActions, type AssetAction} from './useAssetActions';

vi.mock('next/navigation', () => ({
    useRouter: () => ({push: vi.fn()}),
}));
vi.mock('@/components/modals/ModalProvider', () => ({
    useModals: () => ({openModal: vi.fn()}),
}));
vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({isAuthenticated: true}),
}));
vi.mock('@/features/assets/useAssetOpener', () => ({
    useAssetOpener: () => vi.fn(),
}));

type Caps = Partial<Asset['capabilities']>;

function asset(id: string, caps: Caps = {}, source = true): Asset {
    return {
        id,
        workspace: {id: 'ws'},
        capabilities: {
            edit: true,
            delete: true,
            editAttributes: true,
            share: true,
            ...caps,
        },
        source: source ? {url: `https://files/${id}`} : undefined,
    } as unknown as Asset;
}

const i18n = createI18n('en');
const wrapper = ({children}: PropsWithChildren) => (
    <I18nextProvider i18n={i18n}>{children}</I18nextProvider>
);

function actionsOf(assets: Asset[]): Record<string, AssetAction> {
    const {result} = renderHook(() => useAssetActions(assets), {wrapper});

    return Object.fromEntries(result.current.flat().map(a => [a.id, a]));
}

describe('useAssetActions', () => {
    it('enables everything on a single asset with all capabilities', () => {
        const actions = actionsOf([asset('a')]);
        for (const id of [
            'open',
            'info',
            'download',
            'save-as',
            'edit',
            'share',
            'move',
            'copy',
            'replace',
            'delete',
        ]) {
            expect(actions[id], id).toBeDefined();
            expect(actions[id].disabled, id).toBeFalsy();
        }
        expect(actions.download.label).toBe('Download');
        expect(actions.edit.label).toBe('Edit');
    });

    it('greys out what a single asset does not allow', () => {
        const actions = actionsOf([
            asset('a', {delete: false, editAttributes: false}, false),
        ]);
        expect(actions.delete.disabled).toBe(true);
        expect(actions.edit.disabled).toBe(true);
        expect(actions.download.disabled).toBe(true);
        expect(actions['save-as'].disabled).toBe(true);
        expect(actions.share.disabled).toBeFalsy();
    });

    it('greys out single-asset actions on a selection', () => {
        const actions = actionsOf([asset('a'), asset('b')]);
        for (const id of ['open', 'info', 'save-as', 'replace']) {
            expect(actions[id].disabled, id).toBe(true);
        }
        for (const id of [
            'download',
            'edit',
            'share',
            'move',
            'copy',
            'delete',
        ]) {
            expect(actions[id].disabled, id).toBeFalsy();
            expect(actions[id].bulk, id).toBe(true);
        }
        expect(actions.download.label).toBe('Export');
        expect(actions.edit.label).toBe('Edit attributes');
    });

    it('greys out an action one selected asset does not allow', () => {
        const actions = actionsOf([asset('a'), asset('b', {delete: false})]);
        expect(actions.delete.disabled).toBe(true);
        expect(actions.move.disabled).toBeFalsy();
    });

    it('greys out the export when no selected asset has a file', () => {
        const actions = actionsOf([
            asset('a', {}, false),
            asset('b', {}, false),
        ]);
        expect(actions.download.disabled).toBe(true);
        expect(
            actionsOf([asset('a', {}, false), asset('b')]).download.disabled
        ).toBeFalsy();
    });
});
