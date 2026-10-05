import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {Asset, Workspace} from '@/types/api';
import {ExportDialog} from './ExportDialog';

const {misc, collections, utils} = vi.hoisted(() => ({
    misc: {
        getRenditionDefinitions: vi.fn(),
        exportAssets: vi.fn(),
    },
    collections: {
        getWorkspace: vi.fn(),
        getWorkspaces: vi.fn(),
        signWorkspaceTerms: vi.fn(),
    },
    utils: {downloadUrl: vi.fn()},
}));

vi.mock('@/lib/api/misc', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...misc,
}));
vi.mock('@/lib/api/collections', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...collections,
}));
vi.mock('@/lib/utils/misc', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...utils,
}));

// Used by the Radix checkbox, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

function workspace(id: string, signed: boolean | null): Workspace {
    return {
        id,
        name: `Workspace ${id}`,
        terms:
            signed === null
                ? null
                : {text: `Terms of ${id}`, version: 3, signed},
    } as unknown as Workspace;
}

function asset(id: string, workspaceId: string): Asset {
    return {id, workspace: {id: workspaceId}} as unknown as Asset;
}

function renderDialog(assets: Asset[]) {
    const resolve = vi.fn();
    act(() => {
        render(
            <QueryClientProvider client={new QueryClient()}>
                <I18nextProvider i18n={createI18n('en')}>
                    <ExportDialog
                        open
                        onOpenChange={() => undefined}
                        resolve={resolve}
                        assets={assets}
                    />
                </I18nextProvider>
            </QueryClientProvider>
        );
    });

    return {resolve};
}

describe('ExportDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        misc.getRenditionDefinitions.mockResolvedValue({
            items: [
                {
                    id: 'rd1',
                    name: 'Preview',
                    workspace: {id: 'a', name: 'Workspace a'},
                },
            ],
        });
        misc.exportAssets.mockResolvedValue({id: 'e1'});
        collections.getWorkspaces.mockResolvedValue({items: []});
        collections.signWorkspaceTerms.mockResolvedValue({});
    });

    it('signs the unsigned terms before exporting', async () => {
        collections.getWorkspace.mockImplementation((id: string) =>
            Promise.resolve(workspace(id, id === 'b'))
        );
        const {resolve} = renderDialog([asset('1', 'a'), asset('2', 'b')]);

        await screen.findByText('Terms of a');
        // Signed already: nothing to accept
        expect(screen.queryByText('Terms of b')).toBeNull();

        fireEvent.click(screen.getByRole('checkbox', {name: 'Preview'}));
        const submit = screen.getByRole('button', {name: 'Export'});
        expect(submit).toHaveProperty('disabled', true);

        fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'I have read and accept the Terms & Conditions (version 3)',
            })
        );
        expect(submit).toHaveProperty('disabled', false);
        fireEvent.click(submit);

        await waitFor(() => expect(resolve).toHaveBeenCalledWith(['rd1']));
        expect(collections.signWorkspaceTerms).toHaveBeenCalledTimes(1);
        expect(collections.signWorkspaceTerms).toHaveBeenCalledWith('a');
        expect(
            collections.signWorkspaceTerms.mock.invocationCallOrder[0]
        ).toBeLessThan(misc.exportAssets.mock.invocationCallOrder[0]);
    });

    it('exports right away without terms', async () => {
        collections.getWorkspace.mockResolvedValue(workspace('a', null));
        const {resolve} = renderDialog([asset('1', 'a')]);

        fireEvent.click(await screen.findByRole('checkbox', {name: 'Preview'}));
        const submit = screen.getByRole('button', {name: 'Export'});
        await waitFor(() => expect(submit).toHaveProperty('disabled', false));
        fireEvent.click(submit);

        await waitFor(() => expect(resolve).toHaveBeenCalled());
        expect(screen.queryByTestId('export-terms')).toBeNull();
        expect(collections.signWorkspaceTerms).not.toHaveBeenCalled();
    });
});
