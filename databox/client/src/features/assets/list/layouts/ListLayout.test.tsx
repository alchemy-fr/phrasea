import {afterAll, beforeAll, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {AssetList} from '../AssetList';
import {SelectionProvider} from '../SelectionProvider';

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

// jsdom does not implement scrolling
Element.prototype.scrollTo ??= () => undefined;

/** Height of the attributes of `asset-0`, cropped at 240px (`max-h-60`) */
const TALL = 500;

// jsdom has no layout: make the attributes of `asset-0` taller than the clamp
function isTallAttributes(el: HTMLElement): boolean {
    return (
        el.dataset.testid === 'asset-item-attributes' &&
        !!el.closest('[data-asset-id="asset-0"]')
    );
}
const scrollHeight = Object.getOwnPropertyDescriptor(
    Element.prototype,
    'scrollHeight'
)!;
const clientHeight = Object.getOwnPropertyDescriptor(
    Element.prototype,
    'clientHeight'
)!;
const offsetHeight = Object.getOwnPropertyDescriptor(
    HTMLElement.prototype,
    'offsetHeight'
)!;

beforeAll(() => {
    // The virtualizer renders the rows fitting in its scroll container
    Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
        configurable: true,
        get(this: HTMLElement) {
            return this.dataset.testid === 'asset-list' ? 900 : 0;
        },
    });
    Object.defineProperty(HTMLElement.prototype, 'scrollHeight', {
        configurable: true,
        get(this: HTMLElement) {
            return isTallAttributes(this) ? TALL : 0;
        },
    });
    Object.defineProperty(HTMLElement.prototype, 'clientHeight', {
        configurable: true,
        get(this: HTMLElement) {
            if (!isTallAttributes(this)) {
                return 0;
            }

            return this.classList.contains('max-h-60') ? 240 : TALL;
        },
    });
});
afterAll(() => {
    Object.defineProperty(HTMLElement.prototype, 'offsetHeight', offsetHeight);
    Object.defineProperty(HTMLElement.prototype, 'scrollHeight', scrollHeight);
    Object.defineProperty(HTMLElement.prototype, 'clientHeight', clientHeight);
});

function fakeAsset(i: number): Asset {
    return {
        id: `asset-${i}`,
        name: `Asset ${i}`,
        tags: [],
        attributes: [],
        capabilities: {},
        workspace: {id: 'ws'},
    } as unknown as Asset;
}

function renderList(pages: Asset[][]) {
    const i18n = createI18n('en');
    const ui = (p: Asset[][]) => (
        <I18nextProvider i18n={i18n}>
            <SelectionProvider>
                <AssetList
                    pages={p}
                    loading={false}
                    layout="list"
                    thumbSize={200}
                />
            </SelectionProvider>
        </I18nextProvider>
    );
    const utils = render(ui(pages));

    return {...utils, rerenderPages: (p: Asset[][]) => utils.rerender(ui(p))};
}

describe('ListLayout attributes', () => {
    const pages = [[fakeAsset(0), fakeAsset(1)]];
    const row = (container: HTMLElement, i: number) =>
        container.querySelector<HTMLElement>(`[data-asset-id="asset-${i}"]`)!;
    const toggle = (container: HTMLElement, i: number) =>
        row(container, i).querySelector<HTMLButtonElement>(
            '[data-testid="asset-item-attributes-toggle"]'
        );
    const attributes = (container: HTMLElement, i: number) =>
        row(container, i).querySelector<HTMLElement>(
            '[data-testid="asset-item-attributes"]'
        )!;

    it('only offers to expand cropped attributes', () => {
        const {container} = renderList(pages);

        expect(toggle(container, 0)).toBeTruthy();
        expect(toggle(container, 0)!.getAttribute('aria-expanded')).toBe(
            'false'
        );
        expect(toggle(container, 1)).toBeNull();
    });

    it('expands and collapses without selecting the asset', () => {
        const {container} = renderList(pages);

        act(() => {
            fireEvent.click(toggle(container, 0)!);
        });
        expect(toggle(container, 0)!.getAttribute('aria-expanded')).toBe(
            'true'
        );
        expect(attributes(container, 0).classList).not.toContain('max-h-60');
        expect(row(container, 0).getAttribute('data-selected')).toBeNull();

        act(() => {
            fireEvent.click(toggle(container, 0)!);
        });
        expect(toggle(container, 0)!.getAttribute('aria-expanded')).toBe(
            'false'
        );
        expect(attributes(container, 0).classList).toContain('max-h-60');
    });

    it('collapses everything on a new search', () => {
        const {container, rerenderPages} = renderList(pages);

        act(() => {
            fireEvent.click(toggle(container, 0)!);
        });
        expect(toggle(container, 0)!.getAttribute('aria-expanded')).toBe(
            'true'
        );

        rerenderPages([[fakeAsset(0), fakeAsset(1)]]);
        expect(toggle(container, 0)!.getAttribute('aria-expanded')).toBe(
            'false'
        );
    });
});
