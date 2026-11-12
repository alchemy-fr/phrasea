import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {SecretManager} from './SecretManager';

const {secretsApi, integrationsApi} = vi.hoisted(() => ({
    secretsApi: {
        secretsQueryKey: 'workspace-secrets',
        envsQueryKey: 'workspace-envs',
        getWorkspaceSecrets: vi.fn(),
        getWorkspaceEnvs: vi.fn(),
        postWorkspaceSecret: vi.fn(),
        putWorkspaceSecret: vi.fn(),
        deleteWorkspaceSecret: vi.fn(),
        postWorkspaceEnv: vi.fn(),
        putWorkspaceEnv: vi.fn(),
        deleteWorkspaceEnv: vi.fn(),
    },
    integrationsApi: {
        getWorkspaceIntegrations: vi.fn(),
        getGlobalIntegrations: vi.fn(),
    },
}));
vi.mock('@/lib/api/secrets', () => secretsApi);
vi.mock('@/lib/api/integrations', () => integrationsApi);

function page<T>(items: T[]) {
    return {items, total: items.length};
}

function renderManager(workspaceId?: string) {
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <QueryClientProvider client={new QueryClient()}>
                <ModalProvider>
                    <SecretManager workspaceId={workspaceId} />
                </ModalProvider>
            </QueryClientProvider>
        </I18nextProvider>
    );
}

describe('SecretManager', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        secretsApi.getWorkspaceSecrets.mockResolvedValue(
            page([
                {id: 's1', name: 'BG_KEY', createdAt: '', updatedAt: ''},
                {id: 's2', name: 'UNUSED', createdAt: '', updatedAt: ''},
            ])
        );
        secretsApi.getWorkspaceEnvs.mockResolvedValue(
            page([
                {
                    id: 'e1',
                    name: 'BASE_URL',
                    value: 'https://expose.test',
                    createdAt: '',
                    updatedAt: '',
                },
            ])
        );
        secretsApi.deleteWorkspaceSecret.mockResolvedValue(undefined);
        secretsApi.deleteWorkspaceEnv.mockResolvedValue(undefined);
        const integrations = page([
            {
                id: 'i1',
                name: 'Background remover',
                integration: 'remove.bg',
                configValues: {apiKey: '${BG_KEY}'},
            },
            {
                id: 'i2',
                name: null,
                integrationName: 'Webhook',
                integration: 'core.webhook',
                configValues: {url: 'https://$BG_KEY_2/x', base: '${BASE_URL}'},
            },
        ]);
        integrationsApi.getWorkspaceIntegrations.mockResolvedValue(
            integrations
        );
        integrationsApi.getGlobalIntegrations.mockResolvedValue(integrations);
    });

    it('lists the secrets with the integrations referring to them', async () => {
        renderManager('ws');

        await waitFor(() =>
            expect(screen.getByTestId('secret-BG_KEY').textContent).toContain(
                'Used by Background remover'
            )
        );
        expect(screen.getByTestId('secret-UNUSED').textContent).toContain(
            'Not used'
        );
        expect(screen.getByTestId('variable-BASE_URL').textContent).toContain(
            'https://expose.test'
        );
        expect(screen.getByTestId('variable-BASE_URL').textContent).toContain(
            'Used by Webhook'
        );
        expect(secretsApi.getWorkspaceSecrets).toHaveBeenCalledWith(
            {workspaceId: 'ws'},
            expect.anything()
        );
        expect(integrationsApi.getWorkspaceIntegrations).toHaveBeenCalledWith(
            'ws'
        );
    });

    it('lists the instance secrets without workspace', async () => {
        renderManager();

        await waitFor(() => expect(screen.getByTestId('secret-BG_KEY')));
        expect(secretsApi.getWorkspaceSecrets).toHaveBeenCalledWith(
            {workspaceId: undefined},
            expect.anything()
        );
        expect(integrationsApi.getGlobalIntegrations).toHaveBeenCalled();
    });

    it('warns of the integrations using a secret before deleting it', async () => {
        renderManager('ws');

        await waitFor(() =>
            expect(screen.getByTestId('secret-BG_KEY').textContent).toContain(
                'Background remover'
            )
        );
        fireEvent.click(screen.getByTestId('secret-delete-BG_KEY'));
        const dialog = await screen.findByTestId('form-dialog');
        expect(dialog.textContent).toContain('These integrations refer to it');
        expect(dialog.textContent).toContain('Background remover');
        expect(dialog.textContent).not.toContain('Webhook');

        fireEvent.click(screen.getByRole('button', {name: 'Delete'}));
        await waitFor(() =>
            expect(secretsApi.deleteWorkspaceSecret).toHaveBeenCalledWith('s1')
        );
    });

    it('replaces a value without showing the current one', async () => {
        secretsApi.putWorkspaceSecret.mockResolvedValue({
            id: 's1',
            name: 'BG_KEY',
        });
        renderManager('ws');

        fireEvent.click(await screen.findByTestId('secret-replace-BG_KEY'));
        const value = (await screen.findByLabelText(
            'New value'
        )) as HTMLInputElement;
        expect(value.value).toBe('');
        expect(value.type).toBe('password');
        expect(
            (screen.getByLabelText('Name') as HTMLInputElement).readOnly
        ).toBe(true);

        fireEvent.change(value, {target: {value: 'new-key'}});
        fireEvent.click(screen.getByRole('button', {name: 'Save'}));
        await waitFor(() =>
            expect(secretsApi.putWorkspaceSecret).toHaveBeenCalledWith('s1', {
                value: 'new-key',
            })
        );
    });

    it('creates a variable', async () => {
        secretsApi.postWorkspaceEnv.mockResolvedValue({
            id: 'e2',
            name: 'HOST',
            value: 'example.test',
        });
        renderManager('ws');

        fireEvent.click(await screen.findByTestId('variable-create'));
        fireEvent.change(await screen.findByLabelText('Name'), {
            target: {value: 'HOST'},
        });
        fireEvent.change(screen.getByLabelText('Value'), {
            target: {value: 'example.test'},
        });
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));
        await waitFor(() =>
            expect(secretsApi.postWorkspaceEnv).toHaveBeenCalledWith({
                workspaceId: 'ws',
                name: 'HOST',
                value: 'example.test',
            })
        );
    });

    it('edits the value of a variable, shown in clear', async () => {
        secretsApi.putWorkspaceEnv.mockResolvedValue({
            id: 'e1',
            name: 'BASE_URL',
            value: 'https://other.test',
        });
        renderManager('ws');

        fireEvent.click(await screen.findByTestId('variable-edit-BASE_URL'));
        const value = (await screen.findByLabelText(
            'Value'
        )) as HTMLInputElement;
        expect(value.value).toBe('https://expose.test');
        expect(
            (screen.getByLabelText('Name') as HTMLInputElement).readOnly
        ).toBe(true);

        fireEvent.change(value, {target: {value: 'https://other.test'}});
        fireEvent.click(screen.getByRole('button', {name: 'Save'}));
        await waitFor(() =>
            expect(secretsApi.putWorkspaceEnv).toHaveBeenCalledWith('e1', {
                value: 'https://other.test',
            })
        );
    });

    it('warns of the integrations using a variable before deleting it', async () => {
        renderManager('ws');

        await waitFor(() =>
            expect(
                screen.getByTestId('variable-BASE_URL').textContent
            ).toContain('Webhook')
        );
        fireEvent.click(screen.getByTestId('variable-delete-BASE_URL'));
        const dialog = await screen.findByTestId('form-dialog');
        expect(dialog.textContent).toContain('These integrations refer to it');
        expect(dialog.textContent).toContain('Webhook');

        fireEvent.click(screen.getByRole('button', {name: 'Delete'}));
        await waitFor(() =>
            expect(secretsApi.deleteWorkspaceEnv).toHaveBeenCalledWith('e1')
        );
    });
});
