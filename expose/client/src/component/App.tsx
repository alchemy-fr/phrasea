import React from 'react';
import {getAuthRedirect, unsetAuthRedirect} from "../lib/oauth";
import {UserInfoResponse, AuthEventHandler} from "@alchemy/auth";
import {DashboardMenu} from "@alchemy/react-ps";
import {oauthClient} from "../lib/api-client";
import config from "../lib/config";
import AnalyticsRouterProvider from "./anaytics/AnalyticsRouterProvider";
import {useMatomo} from "@jonkoops/matomo-tracker-react";
import AuthorizationCodePage from "@alchemy/auth/src/components/AuthorizationCodePage.tsx";
import RouterProvider from "@alchemy/navigation/src/RouterProvider.tsx";
import {routes} from "../routes.ts";
import AnalyticsRouteProxy from "@alchemy/navigation/src/proxy/AnalyticsRouteProxy.tsx";


type Props = {};

export default function App({}: Props) {
    const { pushInstruction } = useMatomo();
    const [user, setUser] = React.useState<UserInfoResponse | undefined>();

    const authenticate = React.useCallback(async () => {
        setUser(oauthClient.isAuthenticated() ? oauthClient.getDecodedToken()! : undefined);
    }, [user]);

    React.useEffect(() => {
        pushInstruction('setUserId', user?.sub || null);
    }, [user]);

    const onLogin = React.useCallback<AuthEventHandler>(async () => {
        await authenticate();
    }, [authenticate]);
    const onLogout = React.useCallback<AuthEventHandler>(async () => {
        setUser(undefined);
    }, []);

    React.useEffect(() => {
        if (oauthClient.getAccessToken()) {
            authenticate();
        }
        oauthClient.registerListener('login', onLogin);
        oauthClient.registerListener('logout', onLogout);

        return () => {
            oauthClient.unregisterListener('login', onLogin);
            oauthClient.unregisterListener('logout', onLogout);
        }
    }, [onLogin, onLogout, authenticate]);

    const css = config.globalCSS;

    return <>
        <AnalyticsRouterProvider>
            {css && <style>
                {css}
            </style>}
            {config.displayServicesMenu && <DashboardMenu
                dashboardBaseUrl={config.dashboardBaseUrl}
            />}
            <RouterProvider
                routes={routes}
                RouteProxyComponent={AnalyticsRouteProxy}
            />
        </AnalyticsRouterProvider>
    </>
}

const OAuthR = (props: {}) => {
    return <AuthorizationCodePage
        {...props as any}
        oauthClient={oauthClient}
        successHandler={() => {
            const redirectUri = getAuthRedirect() || '/';
            unsetAuthRedirect();
            if (window.opener) {
                try {
                    if (window.opener.isPhraseaApp) {
                        window.opener.document.location.href = redirectUri;
                        window.close();
                    }

                    return;
                } catch (err) {
                    console.error(err);
                }
            }

            history.replace(redirectUri);
        }}
    />
};
