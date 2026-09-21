import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {AssetStatus} from '@/types/api';
import {ModalProvider} from '@/components/modals/ModalProvider';
import {QuarantineScreen} from './QuarantineScreen';
import {useQuarantineQueueStore} from './quarantineQueue';

vi.mock('next/navigation', () => ({
    usePathname: () => '/quarantine',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

vi.mock('next/link', () => ({
    default: ({href, children, ...props}: any) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
}));

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({
        status: 'authenticated',
        isAuthenticated: true,
        hasRole: () => true,
        login: vi.fn(),
        logout: vi.fn(),
    }),
    AppRole: {},
}));

const {api} = vi.hoisted(() => ({
    api: {
        searchAssets: vi.fn(),
        getAssetDuplicates: vi.fn(),
        bypassQuarantine: vi.fn(),
    },
}));

vi.mock('@/lib/api/assets', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/assets')>();

    return {...actual, ...api};
});

function quarantined(i: number): Asset {
    return {
        'id': `q-${i}`,
        '@id': `/assets/q-${i}`,
        'name': `Quarantined ${i}`,
        'status': AssetStatus.Quarantined,
        'createdAt': `2026-01-0${i}T10:00:00+00:00`,
        'capabilities': {},
        'attributes': [],
        'tags': [],
        'collections': [],
        'workspace': {id: 'ws', name: 'Workspace'},
        'source': {
            id: `file-${i}`,
            type: 'image/png',
            accepted: false,
            analysis: {
                status: 'failed',
                results: [
                    {
                        name: 'checksum',
                        output: {
                            messages: [[3, 'duplicate_checksum', {count: 1}]],
                        },
                    },
                ],
            },
        },
    } as unknown as Asset;
}

function renderScreen() {
    const i18n = createI18n('en');
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={i18n}>
                <ModalProvider>
                    <QuarantineScreen />
                </ModalProvider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

const queueItems = () =>
    screen.queryAllByTestId('quarantine-queue-item').length;

describe('QuarantineScreen', () => {
    beforeEach(() => {
        // Resolved ids live in a store shared by every screen of the session
        useQuarantineQueueStore.getState().reset();
        vi.clearAllMocks();
    });

    it('reviews the queue and moves on to the next asset once resolved', async () => {
        const assets = [quarantined(1), quarantined(2)];
        api.searchAssets.mockResolvedValue({
            items: assets,
            total: assets.length,
            facets: {},
        });
        api.getAssetDuplicates.mockResolvedValue([]);
        api.bypassQuarantine.mockResolvedValue({
            ...assets[0],
            status: AssetStatus.Accepted,
        });

        renderScreen();

        // The queue only holds quarantined assets
        await waitFor(() => expect(queueItems()).toBe(2));
        expect(api.searchAssets).toHaveBeenCalledWith(
            expect.objectContaining({conditions: ['@assetStatus = 2']})
        );

        // The head of the queue is the one being reviewed, with its report
        const details = screen.getByTestId('quarantine-details');
        expect(details.textContent).toContain('Quarantined 1');
        expect(details.textContent).toContain('checksum');
        expect(details.textContent).toContain('duplicate checksum (count: 1)');

        fireEvent.click(screen.getByTestId('quarantine-bypass'));

        // Resolved: out of the queue, and the next one takes its place
        await waitFor(() =>
            expect(
                screen.getByTestId('quarantine-details').textContent
            ).toContain('Quarantined 2')
        );
        expect(api.bypassQuarantine).toHaveBeenCalledWith('q-1');
        expect(queueItems()).toBe(1);
    });

    it('keeps resolved assets out of the queue when the screen is revisited', async () => {
        const assets = [quarantined(1), quarantined(2)];
        // The search index lags behind: the asset is still listed as quarantined
        api.searchAssets.mockResolvedValue({
            items: assets,
            total: assets.length,
            facets: {},
        });
        api.getAssetDuplicates.mockResolvedValue([]);
        api.bypassQuarantine.mockResolvedValue({
            ...assets[0],
            status: AssetStatus.Accepted,
        });

        const first = renderScreen();
        await waitFor(() => expect(queueItems()).toBe(2));
        fireEvent.click(screen.getByTestId('quarantine-bypass'));
        await waitFor(() => expect(queueItems()).toBe(1));
        first.unmount();

        renderScreen();
        await waitFor(() =>
            expect(screen.getByTestId('quarantine-details')).toBeTruthy()
        );
        expect(queueItems()).toBe(1);
        expect(screen.getByTestId('quarantine-details').textContent).toContain(
            'Quarantined 2'
        );
    });

    it('tells the user when nothing is quarantined', async () => {
        api.searchAssets.mockResolvedValue({items: [], total: 0, facets: {}});
        api.getAssetDuplicates.mockResolvedValue([]);

        renderScreen();

        const empty = await screen.findByTestId('quarantine-empty');
        expect(empty.textContent).toContain('Nothing in quarantine');
    });
});
