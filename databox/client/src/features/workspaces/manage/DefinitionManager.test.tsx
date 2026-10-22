import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import type {ComponentProps} from 'react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@/components/modals/ModalProvider';
import {DefinitionManager} from './DefinitionManager';
import {RoutePathProvider} from '@/lib/navigation/routePath';

vi.mock('next/navigation', () => ({
    usePathname: () => '/workspaces/ws/manage/attributes',
}));

const {toast} = vi.hoisted(() => ({
    toast: {success: vi.fn(), error: vi.fn()},
}));

vi.mock('sonner', () => ({toast}));

type Item = {id: string; name: string};

const items: Item[] = [
    {id: 'a', name: 'Alpha'},
    {id: 'b', name: 'Bravo'},
];

/** Opens the "…" menu of a row (keyboard, jsdom has no pointer events) */
function openRowMenu(index: number) {
    fireEvent.keyDown(screen.getAllByTestId('definition-menu')[index], {
        key: 'Enter',
    });

    return screen.findByTestId('definition-duplicate');
}

let observers: IntersectionObserverCallback[] = [];

beforeAll(() => {
    globalThis.IntersectionObserver = class {
        constructor(cb: IntersectionObserverCallback) {
            observers.push(cb);
        }
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof IntersectionObserver;
});

/** Makes the end-of-list sentinel visible */
function reachEnd() {
    act(() =>
        observers.forEach(cb =>
            cb(
                [{isIntersecting: true} as IntersectionObserverEntry],
                {} as IntersectionObserver
            )
        )
    );
}

function renderManager(
    props: Partial<ComponentProps<typeof DefinitionManager<Item>>> & {
        /** The URL below the screen */
        segments?: string[];
    } = {}
) {
    const {segments, ...rest} = props;
    const manager = (
        <DefinitionManager<Item>
            items={items}
            renderItem={i => <span>{i.name}</span>}
            renderForm={i => <div data-testid="form">{i?.name ?? 'new'}</div>}
            onDelete={async () => undefined}
            onChanged={() => undefined}
            {...rest}
        />
    );

    return render(
        <QueryClientProvider
            client={
                new QueryClient({defaultOptions: {queries: {retry: false}}})
            }
        >
            <I18nextProvider i18n={createI18n('en')}>
                <ModalProvider>
                    {segments ? (
                        <RoutePathProvider
                            value={{segments, navigate: () => undefined}}
                        >
                            {manager}
                        </RoutePathProvider>
                    ) : (
                        manager
                    )}
                </ModalProvider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('DefinitionManager', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        observers = [];
    });

    it('has no duplicate action without onDuplicate', () => {
        renderManager({});

        expect(screen.getAllByTestId('definition-item')).toHaveLength(2);
        expect(screen.queryAllByTestId('definition-menu')).toHaveLength(0);
    });

    it('duplicates an item, then selects the copy', async () => {
        const copy: Item = {id: 'c', name: 'Alpha (copy)'};
        const onDuplicate = vi.fn(async () => copy);
        const onChanged = vi.fn();

        renderManager({onDuplicate, onChanged});

        expect(screen.getAllByTestId('definition-menu')).toHaveLength(2);
        const item = await openRowMenu(0);
        expect(item.textContent?.trim()).toBe('Duplicate');

        fireEvent.click(item);

        await waitFor(() => expect(onChanged).toHaveBeenCalledTimes(1));
        expect(onDuplicate).toHaveBeenCalledWith(items[0]);
        // Not in the (not yet refreshed) list, the copy is still edited
        await waitFor(() =>
            expect(screen.getByTestId('form').textContent).toBe('Alpha (copy)')
        );
        expect(toast.success).toHaveBeenCalledWith('Copy created');
    });

    it('reports a failed duplication', async () => {
        const onDuplicate = vi.fn(async () => {
            throw new Error('Forbidden');
        });
        const onChanged = vi.fn();

        renderManager({onDuplicate, onChanged});

        fireEvent.click(await openRowMenu(1));

        await waitFor(() =>
            expect(toast.error).toHaveBeenCalledWith('Forbidden')
        );
        expect(onDuplicate).toHaveBeenCalledWith(items[1]);
        expect(onChanged).not.toHaveBeenCalled();
        expect(screen.queryByTestId('form')).toBeNull();
    });

    it('hands a controlled search to the API, without filtering', () => {
        const onQueryChange = vi.fn();
        renderManager({
            query: 'zz',
            onQueryChange,
            filter: (i, q) => i.name.toLowerCase().includes(q),
        });

        // Nothing matches "zz" locally: the items are the API results
        expect(screen.getAllByTestId('definition-item')).toHaveLength(2);
        fireEvent.change(screen.getByPlaceholderText('Filter…'), {
            target: {value: 'zzz'},
        });
        expect(onQueryChange).toHaveBeenCalledWith('zzz');
    });

    it('asks for more items at the end of the list', () => {
        const onReachEnd = vi.fn();
        renderManager({hasMore: true, onReachEnd});

        expect(screen.getByTestId('definition-more')).toBeTruthy();
        reachEnd();

        expect(onReachEnd).toHaveBeenCalled();
    });

    it('reorders a complete list only, without search', () => {
        const onSort = vi.fn(async () => undefined);
        const {unmount} = renderManager({onSort});
        expect(screen.getAllByLabelText('Drag')).toHaveLength(2);
        unmount();

        const onLoadAll = vi.fn(async () => undefined);
        const partial = renderManager({onSort, complete: false, onLoadAll});
        expect(screen.queryAllByLabelText('Drag')).toHaveLength(0);
        fireEvent.click(screen.getByText('Load all to reorder'));
        expect(onLoadAll).toHaveBeenCalled();
        partial.unmount();

        renderManager({onSort, query: 'a', onQueryChange: () => undefined});
        expect(screen.queryAllByLabelText('Drag')).toHaveLength(0);
    });

    it('loads the item of the URL when it is not in the pages', async () => {
        const getItem = vi.fn(async (id: string) => ({id, name: 'Zulu'}));
        renderManager({segments: ['z'], getItem, itemQueryKey: ['items']});

        await waitFor(() =>
            expect(screen.getByTestId('form').textContent).toBe('Zulu')
        );
        expect(getItem).toHaveBeenCalledWith('z');
    });

    it('tells an item of the URL does not exist when it cannot be loaded', async () => {
        const getItem = vi.fn(async () => {
            throw new Error('Not found');
        });
        renderManager({segments: ['z'], getItem});

        expect(
            await screen.findByText('This item does not exist anymore.')
        ).toBeTruthy();
        expect(screen.queryByTestId('form')).toBeNull();
    });
});
