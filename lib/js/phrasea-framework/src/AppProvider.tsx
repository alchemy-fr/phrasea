import {PropsWithChildren} from 'react';
import AnalyticsProvider from './AnalyticsProvider';
import {MatomoInstance} from '@jonkoops/matomo-tracker-react/src/types.ts';
import {ToastContainer} from 'react-toastify';
import {AuthenticationProvider} from '@alchemy/react-auth';
import {ModalStack} from '@alchemy/navigation';
import {KeycloakClient, OAuthClient} from '@alchemy/auth';
import UserHookCaller from './UserHookCaller';
import type {WindowConfig} from '@alchemy/core'
import {AppGlobalTheme} from './Theme/AppGlobalTheme';

type Props = PropsWithChildren<{
    config: WindowConfig;
    matomo: MatomoInstance | undefined;
    oauthClient: OAuthClient<any>;
    keycloakClient: KeycloakClient;
    includeGlobalTheme?: boolean;
}>;

export function AppProvider({
    config,
    oauthClient,
    keycloakClient,
    matomo,
    children,
    includeGlobalTheme = true,
}: Props) {
    const css = config.globalCSS;

    const sub = (
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
    );

        return (
            <>
                {css && <style>{css}</style>}
                {includeGlobalTheme ? <AppGlobalTheme>
                    {sub}
                </AppGlobalTheme> : sub}
            </>
        );
}
