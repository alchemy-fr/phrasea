import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import {ApiError} from '@/lib/api/http';
import {clientIdOf, CreateOAuthClientDialog} from './CreateOAuthClientDialog';

const {api, toast} = vi.hoisted(() => ({
    api: {createOAuthClient: vi.fn()},
    toast: {success: vi.fn(), error: vi.fn()},
}));

vi.mock('sonner', () => ({toast}));
vi.mock('@/lib/api/oauthClients', () => api);

function renderDialog(
    props: Partial<Parameters<typeof CreateOAuthClientDialog>[0]> = {}
) {
    const resolve = vi.fn();
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <QueryClientProvider client={new QueryClient()}>
                <CreateOAuthClientDialog
                    open
                    onOpenChange={() => undefined}
                    resolve={resolve}
                    {...props}
                />
            </QueryClientProvider>
        </I18nextProvider>
    );

    return {resolve};
}

const input = (label: string) =>
    screen.getByLabelText(label) as HTMLInputElement;

describe('clientIdOf', () => {
    it('makes a client ID of the integration title', () => {
        expect(clientIdOf('Expose – Équipe com’')).toBe('expose-equipe-com');
        expect(clientIdOf('my.client_v2')).toBe('my.client_v2');
        expect(clientIdOf('  ')).toBe('databox-integration');
        expect(clientIdOf(undefined)).toBe('databox-integration');
    });
});

describe('CreateOAuthClientDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.createOAuthClient.mockImplementation(async d => ({
            id: 'kc-1',
            clientId: d.clientId,
            name: d.name,
            redirectUris: ['https://api-databox.test/integrations/*'],
            redirectUriAllowed: true,
        }));
    });

    it('creates the client named after the integration', async () => {
        const {resolve} = renderDialog({integrationTitle: 'Expose prod'});

        expect(input('Client ID').value).toBe('expose-prod');
        expect(input('Name').value).toBe('Expose prod');

        fireEvent.change(input('Description'), {
            target: {value: 'Publications'},
        });
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(resolve).toHaveBeenCalled());
        expect(api.createOAuthClient).toHaveBeenCalledWith({
            clientId: 'expose-prod',
            name: 'Expose prod',
            description: 'Publications',
        });
        expect(resolve.mock.lastCall?.[0].clientId).toBe('expose-prod');
        expect(toast.success).toHaveBeenCalled();
    });

    it('defaults to a generic client ID without title', () => {
        renderDialog();

        expect(input('Client ID').value).toBe('databox-integration');
        expect(input('Name').value).toBe('');
    });

    it('shows the refusal of the client ID under its field', async () => {
        api.createOAuthClient.mockRejectedValue(
            new ApiError('Validation Failed', 422, {
                violations: [
                    {
                        propertyPath: 'clientId',
                        message: 'A client with this ID already exists.',
                    },
                ],
            })
        );
        const {resolve} = renderDialog({integrationTitle: 'Expose'});

        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() =>
            expect(
                screen.getByText('A client with this ID already exists.')
            ).toBeTruthy()
        );
        expect(input('Client ID').getAttribute('aria-invalid')).toBe('true');
        expect(resolve).not.toHaveBeenCalled();
        expect(toast.error).not.toHaveBeenCalled();
    });

    it('reports the other failures', async () => {
        api.createOAuthClient.mockRejectedValue(new Error('Nope'));
        const {resolve} = renderDialog({integrationTitle: 'Expose'});

        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Nope'));
        expect(resolve).not.toHaveBeenCalled();
    });
});
