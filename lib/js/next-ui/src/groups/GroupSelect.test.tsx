import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '../test/i18n';
import {createFakeGroupsApi} from '../test/groups';
import {GroupSelect} from './GroupSelect';

// cmdk needs them, jsdom has none
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
globalThis.IntersectionObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof IntersectionObserver;
Element.prototype.scrollIntoView ??= () => {};

function renderSelect(value: string[]) {
    const {api} = createFakeGroupsApi([
        {id: 'g-marketing', name: 'Marketing'},
        {id: 'g-sales', name: 'Sales'},
    ]);
    const getGroupsByIds = vi.fn(api.getGroupsByIds);
    const onChange = vi.fn();
    render(
        <QueryClientProvider
            client={
                new QueryClient({defaultOptions: {queries: {retry: false}}})
            }
        >
            <I18nextProvider i18n={createI18n('en')}>
                <GroupSelect
                    api={{...api, getGroupsByIds}}
                    value={value}
                    onChange={onChange}
                />
            </I18nextProvider>
        </QueryClientProvider>
    );

    return {onChange, getGroupsByIds};
}

describe('GroupSelect', () => {
    it('shows the names of the selected groups', async () => {
        const {getGroupsByIds} = renderSelect(['g-sales']);

        expect(await screen.findByText('Sales')).toBeTruthy();
        expect(getGroupsByIds).toHaveBeenCalledWith(['g-sales']);
    });

    it('adds the picked group to the selection', async () => {
        const {onChange} = renderSelect(['g-sales']);
        await screen.findByText('Sales');

        fireEvent.click(screen.getByRole('combobox'));
        fireEvent.click(await screen.findByText('Marketing'));

        await waitFor(() =>
            expect(onChange).toHaveBeenCalledWith(
                ['g-sales', 'g-marketing'],
                [
                    {id: 'g-sales', name: 'Sales'},
                    {id: 'g-marketing', name: 'Marketing'},
                ]
            )
        );
    });

    it('shows the placeholder without a selection', () => {
        const {getGroupsByIds} = renderSelect([]);

        expect(screen.getByText('Select groups…')).toBeTruthy();
        expect(getGroupsByIds).not.toHaveBeenCalled();
    });
});
