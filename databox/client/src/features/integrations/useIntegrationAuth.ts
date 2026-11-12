'use client';

import {useQuery} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {WorkspaceIntegration} from '@/types/api';
import {getIntegrationTokens} from '@/lib/api/integrations';
import {api} from '@/lib/api/http';
import {useConfig} from '@/lib/config/ConfigProvider';

/**
 * A one-time token of the signed-in user: the authorization popup cannot carry
 * the bearer token, the API exchanges this one when Expose redirects back.
 */
async function getOneTimeToken(): Promise<string> {
    // A popup cannot carry the impersonation header: the authorization is
    // always granted as the signed-in user.
    const {token} = await api.post<{token: string}>(
        '/ott',
        {},
        {asRealUser: true}
    );

    return token;
}

/**
 * Whether the user authorized an OAuth integration (e.g. Expose), and the popup
 * to authorize it.
 */
export function useIntegrationAuth(
    integration: Pick<WorkspaceIntegration, 'id'> | undefined
) {
    const config = useConfig();
    const tokens = useQuery({
        queryKey: ['integration-tokens', integration?.id],
        queryFn: () => getIntegrationTokens(integration!.id),
        enabled: !!integration,
    });
    const authorized = (tokens.data?.items ?? []).some(tk => !tk.expired);

    const authorize = async () => {
        if (!integration) {
            return;
        }
        // Opened before any await: a popup opened later is blocked by the browser
        const popup = window.open(
            '',
            'integration-auth',
            'width=600,height=700'
        );
        try {
            const token = await getOneTimeToken();
            const url = `${config.apiUrl}/integrations/${integration.id}/auth?token=${encodeURIComponent(token)}`;
            if (popup) {
                popup.location.href = url;
            }
        } catch (e: any) {
            popup?.close();
            toast.error(e?.message);

            return;
        }
        await new Promise<void>(resolve => {
            const timer = setInterval(() => {
                if (!popup || popup.closed) {
                    clearInterval(timer);
                    resolve();
                }
            }, 500);
        });
        await tokens.refetch();
    };

    return {authorized, authorize, loading: tokens.isLoading};
}
