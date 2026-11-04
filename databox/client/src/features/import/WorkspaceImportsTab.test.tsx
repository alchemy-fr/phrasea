import {beforeEach, describe, expect, it, vi} from 'vitest';
import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@/components/modals/ModalProvider';
import type {AssetImport, Workspace} from '@/types/api';

vi.mock('next/navigation', () => ({
    usePathname: () => '/workspaces/ws-1/manage/imports',
    useRouter: () => ({push: vi.fn()}),
}));

const api = vi.hoisted(() => ({
    getAssetImports: vi.fn(),
    getAssetImportErrors: vi.fn(),
    deleteAssetImport: vi.fn(),
}));

vi.mock('@/lib/api/imports', () => api);

import {importProgress, WorkspaceImportsTab} from './WorkspaceImportsTab';

const workspace = {
    id: 'ws-1',
    capabilities: {createAsset: true},
} as unknown as Workspace;

function makeImport(overrides: Partial<AssetImport>): AssetImport {
    return {
        id: 'imp',
        workspaceId: 'ws-1',
        format: 'ndjson',
        status: 'completed',
        created: 0,
        skipped: 0,
        failed: 0,
        createdAt: '2026-11-03T10:00:00+00:00',
        updatedAt: '2026-11-03T10:05:00+00:00',
        ...overrides,
    };
}

function page<T>(items: T[]) {
    return {total: items.length, items};
}

function renderTab() {
    const client = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });
    act(() => {
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <QueryClientProvider client={client}>
                    <ModalProvider>
                        <WorkspaceImportsTab
                            workspace={workspace}
                            refresh={() => undefined}
                        />
                    </ModalProvider>
                </QueryClientProvider>
            </I18nextProvider>
        );
    });
}

describe('WorkspaceImportsTab', () => {
    beforeEach(() => {
        Object.values(api).forEach(f => f.mockReset());
    });

    it('shows an empty state', async () => {
        api.getAssetImports.mockResolvedValue(page([]));

        renderTab();

        expect(await screen.findByTestId('asset-imports-empty')).toBeTruthy();
    });

    it('lists the imports with their author and counters', async () => {
        api.getAssetImports.mockResolvedValue(
            page([
                makeImport({
                    id: 'a',
                    originalName: 'photos.ndjson',
                    status: 'processing',
                    owner: {id: 'u1', username: 'alice'},
                    total: 10,
                    created: 3,
                    skipped: 1,
                    failed: 1,
                }),
                makeImport({
                    id: 'b',
                    originalName: 'feed.json',
                    integrationTitle: 'Nightly feed',
                }),
            ])
        );

        renderTab();

        const rows = await screen.findAllByTestId('asset-import');
        expect(rows).toHaveLength(2);
        expect(rows[0].textContent).toContain('photos.ndjson');
        expect(rows[0].textContent).toContain('alice');
        expect(rows[0].textContent).toContain('Processing');
        expect(
            within(rows[0]).getByTestId('asset-import-counts').textContent
        ).toBe('3 created · 1 skipped · 1 failed · 10 total');
        expect(within(rows[0]).getByRole('progressbar')).toBeTruthy();
        expect(rows[1].textContent).toContain('Nightly feed');
        expect(
            within(rows[1]).getByTestId('asset-import-counts').textContent
        ).toContain('? total');
        expect(within(rows[1]).queryByRole('progressbar')).toBeNull();
        expect(api.getAssetImports).toHaveBeenCalledWith('ws-1', 1);
    });

    it('loads the errors of an expanded import', async () => {
        api.getAssetImports.mockResolvedValue(
            page([makeImport({id: 'a', originalName: 'x.ndjson', failed: 1})])
        );
        api.getAssetImportErrors.mockResolvedValue(
            page([{id: 'e1', line: 4, key: 'k4', message: 'Invalid URL'}])
        );

        renderTab();
        const row = await screen.findByTestId('asset-import');
        act(() => {
            fireEvent.click(within(row).getByText('x.ndjson'));
        });

        const table = await screen.findByTestId('asset-import-errors');
        expect(table.textContent).toContain('k4');
        expect(table.textContent).toContain('Invalid URL');
        expect(api.getAssetImportErrors).toHaveBeenCalledWith('a', 1);
    });

    it('deletes an import once confirmed', async () => {
        api.getAssetImports.mockResolvedValue(
            page([makeImport({id: 'a', originalName: 'x.ndjson'})])
        );
        api.deleteAssetImport.mockResolvedValue(undefined);

        renderTab();
        const row = await screen.findByTestId('asset-import');
        act(() => {
            within(row).getByRole('button', {name: 'Delete'}).click();
        });
        const dialog = await screen.findByTestId('form-dialog');
        act(() => {
            within(dialog).getByRole('button', {name: 'Delete'}).click();
        });

        await waitFor(() =>
            expect(api.deleteAssetImport).toHaveBeenCalledWith('a')
        );
    });
});

describe('importProgress', () => {
    it('is unknown without a total', () => {
        expect(importProgress(makeImport({}))).toBeUndefined();
    });

    it('counts every entry done', () => {
        expect(
            importProgress(
                makeImport({total: 8, created: 4, skipped: 1, failed: 1})
            )
        ).toBe(75);
    });
});
