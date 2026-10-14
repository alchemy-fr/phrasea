import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {Workspace} from '@/types/api';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {WorkspaceTermsGate} from './WorkspaceTermsGate';

const {api} = vi.hoisted(() => ({
    api: {
        getWorkspaces: vi.fn(),
        getWorkspace: vi.fn(),
        signWorkspaceTerms: vi.fn(),
    },
}));

vi.mock('@/lib/api/collections', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...api,
}));

// Used by the Radix checkbox, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

function workspace(id: string, termsUnsigned: boolean): Workspace {
    return {
        id,
        name: `Workspace ${id}`,
        termsUnsigned,
        terms: {
            text: `Terms of ${id}`,
            version: 2,
            signed: !termsUnsigned,
            attachToExports: false,
        },
    } as unknown as Workspace;
}

const page = (items: Workspace[]) => ({items, total: items.length});

function renderGate() {
    act(() => {
        render(
            <QueryClientProvider client={new QueryClient()}>
                <I18nextProvider i18n={createI18n('en')}>
                    <WorkspaceTermsGate />
                </I18nextProvider>
            </QueryClientProvider>
        );
    });
}

describe('WorkspaceTermsGate', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        useCollectionStore.setState({
            workspaces: [],
            workspacesLoaded: false,
            workspacesLoading: false,
            pagers: {},
        });
        api.getWorkspace.mockImplementation((id: string) =>
            Promise.resolve(workspace(id, true))
        );
        api.signWorkspaceTerms.mockImplementation((id: string) =>
            Promise.resolve(workspace(id, false))
        );
    });

    it('stays hidden when every terms are signed', async () => {
        api.getWorkspaces.mockResolvedValue(page([workspace('a', false)]));
        renderGate();

        await waitFor(() =>
            expect(useCollectionStore.getState().workspacesLoaded).toBe(true)
        );
        expect(screen.queryByTestId('workspace-terms-dialog')).toBeNull();
    });

    it('asks to sign the unsigned terms one by one', async () => {
        api.getWorkspaces.mockResolvedValueOnce(
            page([
                workspace('a', true),
                workspace('b', false),
                workspace('c', true),
            ])
        );
        renderGate();

        await screen.findByText('Terms of a');
        expect(screen.getByText('Not now (2 pending)')).toBeTruthy();
        const accept = screen.getByRole('button', {name: 'Accept'});
        expect(accept).toHaveProperty('disabled', true);

        fireEvent.click(screen.getByRole('checkbox'));
        expect(accept).toHaveProperty('disabled', false);

        api.getWorkspaces.mockResolvedValueOnce(
            page([
                workspace('a', false),
                workspace('b', false),
                workspace('c', true),
            ])
        );
        fireEvent.click(accept);

        await screen.findByText('Terms of c');
        expect(api.signWorkspaceTerms).toHaveBeenCalledWith('a');
        expect(screen.getByText('Not now')).toBeTruthy();
        // A new dialog: the previous acceptance is not carried over
        expect(screen.getByRole('checkbox').getAttribute('aria-checked')).toBe(
            'false'
        );
    });

    it('can be dismissed', async () => {
        api.getWorkspaces.mockResolvedValue(page([workspace('a', true)]));
        renderGate();

        await screen.findByText('Terms of a');
        fireEvent.click(screen.getByRole('button', {name: 'Not now'}));

        await waitFor(() =>
            expect(screen.queryByTestId('workspace-terms-dialog')).toBeNull()
        );
        expect(api.signWorkspaceTerms).not.toHaveBeenCalled();
    });
});
