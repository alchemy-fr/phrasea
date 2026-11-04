import {describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {DropdownMenu, DropdownMenuContent} from '@/components/ui/menu';
import {AssetMenuItems} from './AssetContextMenu';

vi.mock('@/features/assets/actions/useAssetActions', () => ({
    useAssetActions: () => [
        [{id: 'open', label: 'Open', icon: null, run: vi.fn()}],
    ],
}));

// jsdom implements neither of these, and Radix's menu needs both
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => undefined;

const [a, b, c] = ['a', 'b', 'c'].map(id => ({id}) as Asset);

function renderMenu(assets: Asset[]) {
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <DropdownMenu open>
                <DropdownMenuContent>
                    <AssetMenuItems assets={assets} variant="dropdown" />
                </DropdownMenuContent>
            </DropdownMenu>
        </I18nextProvider>
    );
}

describe('AssetMenuItems', () => {
    it('gives the count of a multiple selection above the actions', () => {
        renderMenu([a, b, c]);
        expect(screen.getByTestId('menu-selection-count').textContent).toBe(
            '3 assets selected'
        );
        expect(screen.getByText('Open')).toBeTruthy();
    });

    it('has no header for a single asset', () => {
        renderMenu([a]);
        expect(screen.queryByTestId('menu-selection-count')).toBeNull();
        expect(screen.getByText('Open')).toBeTruthy();
    });
});
