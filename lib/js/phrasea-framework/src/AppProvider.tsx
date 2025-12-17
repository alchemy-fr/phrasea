import {PropsWithChildren} from 'react';
import {AppGlobalStyles} from '@alchemy/phrasea-ui';
import AnalyticsProvider from './AnalyticsProvider';
import {MatomoInstance} from '@jonkoops/matomo-tracker-react/src/types.ts';
import {ToastContainer} from 'react-toastify';
import {AuthenticationProvider} from '@alchemy/react-auth';
import {ModalStack} from '@alchemy/navigation';
import {KeycloakClient, OAuthClient} from '@alchemy/auth';
import UserHookCaller from './UserHookCaller';
import type {WindowConfig} from '@alchemy/core'

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
    matomo, children
}: Props) {
    const css = config.globalCSS;

    return (
        <>
            {css && <style>{css}</style>}
            <AppGlobalStyles />
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
        </>
    );
}
