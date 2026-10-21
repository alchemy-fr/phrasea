import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
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
