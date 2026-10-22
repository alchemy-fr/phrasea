import {afterEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import type {ComponentProps} from 'react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import {AsyncCombobox, type ComboOption} from './AsyncCombobox';

// cmdk needs them, jsdom has none
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => {};

// The sentinel of the infinite scroll: `reachEnd()` makes it visible
let observers: IntersectionObserverCallback[] = [];
globalThis.IntersectionObserver = class {
    constructor(cb: IntersectionObserverCallback) {
        observers.push(cb);
    }
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof IntersectionObserver;

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

afterEach(() => {
    observers = [];
});

const sizes: ComboOption = {value: 'sizes', label: 'Sizes'};

type Props = Partial<ComponentProps<typeof AsyncCombobox>>;

function renderCombobox(props: Props = {}) {
    const onChange = vi.fn();
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });
    render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={createI18n('en')}>
                <AsyncCombobox
                    queryKey={['test']}
                    loadOptions={async () => [sizes]}
                    value={undefined}
                    onChange={onChange}
                    createLabel={q => (q ? `Create "${q}"` : 'Create new')}
                    {...(props as object)}
                />
            </I18nextProvider>
        </QueryClientProvider>
    );

    return {onChange};
}

async function openList() {
    fireEvent.click(screen.getByRole('combobox'));
    await screen.findByText('Sizes');
}

describe('AsyncCombobox', () => {
    it('offers the creation only from a typed term by default', async () => {
        renderCombobox({onCreate: vi.fn()});
        await openList();

        expect(screen.queryByText('Create new')).toBeNull();

        fireEvent.change(screen.getByPlaceholderText('Search…'), {
            target: {value: 'x'},
        });
        expect(await screen.findByText('Create "x"')).toBeTruthy();
    });

    it('creates with an empty search when createWithoutQuery is set', async () => {
        const created: ComboOption = {value: 'faces', label: 'Faces'};
        const onCreate = vi.fn(async () => created);
        const {onChange} = renderCombobox({onCreate, createWithoutQuery: true});
        await openList();

        fireEvent.click(screen.getByText('Create new'));

        expect(onCreate).toHaveBeenCalledWith('');
        await waitFor(() =>
            expect(onChange).toHaveBeenCalledWith('faces', created)
        );
    });
});

describe('AsyncCombobox with pages', () => {
    const option = (v: string): ComboOption => ({
        value: v,
        label: `Option ${v}`,
    });

    it('loads the next page when scrolling to the end', async () => {
        const loadPage = vi.fn(
            async (_q: string, _s: AbortSignal, cursor?: string) =>
                cursor
                    ? {options: [option('3')]}
                    : {options: [option('1'), option('2')], next: 'p2'}
        );
        renderCombobox({loadPage, loadOptions: undefined});
        fireEvent.click(screen.getByRole('combobox'));
        await screen.findByText('Option 2');
        expect(screen.queryByText('Option 3')).toBeNull();

        reachEnd();

        expect(await screen.findByText('Option 3')).toBeTruthy();
        expect(loadPage.mock.calls.map(c => c[2])).toEqual([undefined, 'p2']);
    });

    it('goes on to the next page when one shows nothing', async () => {
        const loadPage = vi.fn(
            async (_q: string, _s: AbortSignal, cursor?: string) =>
                cursor ? {options: [option('9')]} : {options: [], next: 'p2'}
        );
        renderCombobox({loadPage, loadOptions: undefined});
        fireEvent.click(screen.getByRole('combobox'));

        expect(await screen.findByText('Option 9')).toBeTruthy();
        expect(loadPage).toHaveBeenCalledTimes(2);
    });

    it('resolves the labels of the selected values in one call', async () => {
        const resolveValues = vi.fn(async (values: string[]) =>
            values.map(option)
        );
        renderCombobox({
            multiple: true,
            value: ['a', 'b', 'c'],
            loadPage: async () => ({options: []}),
            loadOptions: undefined,
            resolveValues,
        } as Props);

        expect(await screen.findByText('Option c')).toBeTruthy();
        expect(screen.getByText('Option a')).toBeTruthy();
        expect(resolveValues).toHaveBeenCalledTimes(1);
        expect(resolveValues).toHaveBeenCalledWith(['a', 'b', 'c']);
    });
});
