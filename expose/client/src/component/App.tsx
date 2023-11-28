import React from 'react';
import {getAuthRedirect, unsetAuthRedirect} from "../lib/oauth";
import {UserInfoResponse, AuthEventHandler} from "@alchemy/auth";
import {DashboardMenu} from "@alchemy/react-ps";
import {oauthClient} from "../lib/api-client";
import config from "../lib/config";
import {BrowserRouter as Router, Route, Switch, useHistory} from "react-router-dom";
import AnalyticsRouterProvider from "./anaytics/AnalyticsRouterProvider";
import OAuthRedirect from "./OAuthRedirect";
import PublicationIndex from "./index/PublicationIndex";
import EmbeddedAsset from "./EmbeddedAsset";
import PublicationRoute from "./routes/PublicationRoute";
import AssetRoute from "./routes/AssetRoute";
import ErrorPage from "./ErrorPage";
import {useMatomo} from "@jonkoops/matomo-tracker-react";


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

    return <Router>
        <AnalyticsRouterProvider>
            {css && <style>
                {css}
            </style>}
            {config.displayServicesMenu && <DashboardMenu
                dashboardBaseUrl={config.dashboardBaseUrl}
            />}
            <Switch>
                <Route path="/auth" component={OAuthR}/>
                {!config.disableIndexPage && <Route path="/" exact component={PublicationIndex} />}
                <Route path="/embed/:asset" exact render={({match: {params}}) => <EmbeddedAsset
                    id={params.asset}
                />}/>
                <Route path="/:publication" exact render={props => <PublicationRoute
                    {...props}
                    username={user?.preferred_username}
                />}/>
                <Route path="/:publication/:asset" exact render={props => <AssetRoute
                    {...props}
                    username={user?.preferred_username}
                />}/>
                <Route path="/" exact render={() => <ErrorPage
                    title={'Not found'}
                    code={404}
                />}/>
            </Switch>
        </AnalyticsRouterProvider>
    </Router>
}

const OAuthR = (props: {}) => {
    const history = useHistory();

    return <OAuthRedirect
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
