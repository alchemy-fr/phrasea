import {PropsWithChildren} from 'react';
import AnalyticsProvider from './AnalyticsProvider';
import {MatomoInstance} from '@jonkoops/matomo-tracker-react/src/types.ts';
import {ToastContainer, ToastPosition} from 'react-toastify';
import {AuthenticationProvider} from '@alchemy/react-auth';
import {ModalStack} from '@alchemy/navigation';
import {KeycloakClient, OAuthClient} from '@alchemy/auth';
import UserHookCaller from './UserHookCaller';
import {AppGlobalTheme} from './Theme/AppGlobalTheme';
import {HttpClient} from '@alchemy/api';

type Props = PropsWithChildren<{
    config: WindowConfig;
    matomo: MatomoInstance | undefined;
    oauthClient: OAuthClient<any>;
    apiClient: HttpClient;
    keycloakClient: KeycloakClient;
    toastPosition?: ToastPosition;
}>;

export function AppProvider({
    config,
    oauthClient,
    keycloakClient,
    apiClient,
    matomo,
    children,
    toastPosition,
}: Props) {
    const css = config.globalCSS;

    return (
        <>
            {css && <style>{css}</style>}
            <AppGlobalTheme>
                <AnalyticsProvider matomo={matomo}>
                    <ToastContainer position={toastPosition ?? 'bottom-left'} />
                    <AuthenticationProvider
                        oauthClient={oauthClient}
                        keycloakClient={keycloakClient}
                    >
                        <UserHookCaller apiClient={apiClient} />
                        <ModalStack>{children}</ModalStack>
                    </AuthenticationProvider>
                </AnalyticsProvider>
            </AppGlobalTheme>
        </>
    );
}
