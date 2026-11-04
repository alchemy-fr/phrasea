import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {User} from '@/types/api';
import {UserSelect} from './selects';

const {misc} = vi.hoisted(() => ({
    misc: {getUsers: vi.fn(), getGroups: vi.fn()},
}));

vi.mock('@/lib/api/misc', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/misc')>()),
    ...misc,
}));

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

const user = (i: number): User =>
    ({id: `u${i}`, username: `user${i}`}) as unknown as User;

function renderSelect(value: string[]) {
    render(
        <QueryClientProvider
            client={
                new QueryClient({defaultOptions: {queries: {retry: false}}})
            }
        >
            <I18nextProvider i18n={createI18n('en')}>
                <UserSelect multiple value={value} onChange={() => undefined} />
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('UserSelect', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // A first page of 30 users, the selected one being further
        misc.getUsers.mockImplementation(
            async ({ids, offset}: {ids?: string[]; offset?: number}) =>
                ids
                    ? ids.filter(id => id === 'u42').map(() => user(42))
                    : Array.from({length: 30}, (_, i) =>
                          user(i + (offset ?? 0))
                      )
        );
    });

    it('names a selected user beyond the first page, by id', async () => {
        renderSelect(['u42']);

        expect(await screen.findByText('user42')).toBeTruthy();
        expect(misc.getUsers).toHaveBeenCalledTimes(1);
        expect(misc.getUsers).toHaveBeenCalledWith({ids: ['u42']});
    });

    it('pages the users by offset', async () => {
        renderSelect([]);
        fireEvent.click(screen.getByRole('combobox'));

        await screen.findByText('user29');
        await waitFor(() =>
            expect(misc.getUsers).toHaveBeenCalledWith(
                {query: '', offset: 0, limit: 30},
                expect.anything()
            )
        );
    });
});
