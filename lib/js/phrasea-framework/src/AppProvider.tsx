import {PropsWithChildren} from 'react';
import AnalyticsProvider from './AnalyticsProvider';
import {MatomoInstance} from '@jonkoops/matomo-tracker-react/src/types.ts';
import {ToastContainer} from 'react-toastify';
import {AuthenticationProvider} from '@alchemy/react-auth';
import {ModalStack} from '@alchemy/navigation';
import {KeycloakClient, OAuthClient} from '@alchemy/auth';
import UserHookCaller from './UserHookCaller';
import {AppGlobalTheme} from './Theme/AppGlobalTheme';

type Props = PropsWithChildren<{
    config: WindowConfig;
    matomo: MatomoInstance | undefined;
    oauthClient: OAuthClient<any>;
    keycloakClient: KeycloakClient;
}>;

export function AppProvider({
    config,
    oauthClient,
    keycloakClient,
    matomo,
    children,
}: Props) {
    const css = config.globalCSS;

    return (
        <>
            {css && <style>{css}</style>}
            <AppGlobalTheme>
                <AnalyticsProvider matomo={matomo}>
                    <ToastContainer position={'bottom-left'} />
                    <AuthenticationProvider
                        oauthClient={oauthClient}
                        keycloakClient={keycloakClient}
                    >
                        <UserHookCaller />
                        <ModalStack>{children}</ModalStack>
                    </AuthenticationProvider>
                </AnalyticsProvider>
            </AppGlobalTheme>
        </>
    );
}
