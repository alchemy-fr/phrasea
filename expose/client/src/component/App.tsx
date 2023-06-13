import React from 'react';
import {getAuthRedirect, oauthClient, unsetAuthRedirect} from "../lib/oauth";
import {AuthEventHandler, DashboardMenu, UserInfoResponse} from "react-ps";
import config from "../lib/config";
import {BrowserRouter as Router, Route, Switch} from "react-router-dom";
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
    const [user, setUser] = React.useState<UserInfoResponse | null>(null);

    const authenticate = React.useCallback(async () => {
        if (user) {
            return;
        }

        const res = await oauthClient.authenticate();
        setUser(res);
    }, [user]);

    React.useEffect(() => {
        pushInstruction('setUserId', user?.user_id || null);
    }, [user]);

    const onLogin = React.useCallback<AuthEventHandler>(async (event) => {
        await authenticate();
    }, [authenticate]);
    const onLogout = React.useCallback<AuthEventHandler>(async (event) => {
        setUser(null);
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

    const css = config.get('globalCSS');

    return <Router>
        <AnalyticsRouterProvider>
            {css && <style>
                {css}
            </style>}
            {config.get('displayServicesMenu') && <DashboardMenu
                dashboardBaseUrl={config.get('dashboardBaseUrl') as string}
            />}
            <Switch>
                <Route path="/auth/:provider" component={(props: any) => {
                    return <OAuthRedirect
                        {...props}
                        oauthClient={oauthClient}
                        successHandler={(history: any) => {
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
                }}/>
                {!config.get('disableIndexPage') as boolean && <Route path="/" exact component={PublicationIndex}/>}
                <Route path="/embed/:asset" exact render={({match: {params}}) => <EmbeddedAsset
                    id={params.asset}
                />}/>
                <Route path="/:publication" exact render={props => <PublicationRoute
                    {...props}
                    authenticated={user}
                />}/>
                <Route path="/:publication/:asset" exact render={props => <AssetRoute
                    {...props}
                    authenticated={user}
                />}/>
                <Route path="/" exact render={() => <ErrorPage
                    title={'Not found'}
                    code={404}
                />}/>
            </Switch>
        </AnalyticsRouterProvider>
    </Router>
}
