import {Profiler} from 'react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {AssetList} from './AssetList';
import {SelectionProvider} from './SelectionProvider';

const counter = vi.hoisted(() => ({itemRenders: 0}));

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

// Every grid item renders `useLiveAsset` exactly once: count item renders
vi.mock('@/features/assets/assetStore', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/features/assets/assetStore')>();

    return {
        ...actual,
        useLiveAsset: (asset: Asset) => {
            counter.itemRenders++;

            return actual.useLiveAsset(asset);
        },
    };
});

const COUNT = 300;

// jsdom does not implement scrolling
Element.prototype.scrollTo ??= () => undefined;

function fakeAsset(i: number): Asset {
    return {
        id: `asset-${i}`,
        name: `Asset ${i}`,
        tags: [],
        capabilities: {},
        workspace: {id: 'ws'},
    } as unknown as Asset;
}

const pages = [Array.from({length: COUNT}, (_, i) => fakeAsset(i))];

describe('AssetList selection rendering', () => {
    let commitMs = 0;

    beforeEach(() => {
        counter.itemRenders = 0;
        commitMs = 0;
    });

    function renderList() {
        const i18n = createI18n('en');
        const utils = render(
            <I18nextProvider i18n={i18n}>
                <Profiler
                    id="list"
                    onRender={(_id, _phase, actualDuration) => {
                        commitMs += actualDuration;
                    }}
                >
                    <SelectionProvider>
                        <AssetList
                            pages={pages}
                            loading={false}
                            layout="grid"
                            thumbSize={200}
                        />
                    </SelectionProvider>
                </Profiler>
            </I18nextProvider>
        );

        return utils;
    }

    it('only re-renders the items whose selection changed', () => {
        const {container} = renderList();
        const item = (i: number) =>
            container.querySelector(`[data-asset-id="asset-${i}"]`)!;

        // select #10, then #20
        const measure = (i: number) => {
            counter.itemRenders = 0;
            commitMs = 0;
            act(() => {
                fireEvent.click(item(i));
            });

            return {itemRenders: counter.itemRenders, commitMs};
        };
        const first = measure(10);
        const second = measure(20);

        expect(item(20).getAttribute('data-selected')).toBe('true');
        expect(item(10).getAttribute('data-selected')).toBeNull();
        // Nothing but the items whose state changed may render: #10 when it
        // gets selected, then #10 and #20 (was: the whole list, every click)
        expect(first.itemRenders).toBeLessThanOrEqual(1);
        expect(second.itemRenders).toBeLessThanOrEqual(2);
    });
});
