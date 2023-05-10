import React, {PureComponent} from 'react';
import {Route, BrowserRouter as Router, Switch} from "react-router-dom";
import PublicationRoute from "./routes/PublicationRoute";
import PublicationIndex from "./index/PublicationIndex";
import AssetRoute from "./routes/AssetRoute";
import {getAuthRedirect, oauthClient, unsetAuthRedirect} from "../lib/oauth";
import config from "../lib/config";
import ErrorPage from "./ErrorPage";
import OAuthRedirect from "./OAuthRedirect";
import {DashboardMenu} from "react-ps";
import EmbeddedAsset from "./EmbeddedAsset";

class App extends PureComponent {
    state = {
        authenticated: null,
    };

    componentDidMount() {
        this.init();

        oauthClient.registerListener('login', this.onLogin);
        oauthClient.registerListener('logout', this.onLogout);
    }

    componentWillUnmount() {
        oauthClient.unregisterListener('login', this.onLogin);
        oauthClient.unregisterListener('logout', this.onLogout);
    }

    init = () => {
        if (oauthClient.getAccessToken()) {
            this.authenticate();
        }
    }

    onLogin = () => {
        this.authenticate();
    }

    onLogout = async () => {
        this.setState({authenticated: null});
    }

    async authenticate() {
        if (this.state.authenticated) {
            return;
        }

        const res = await oauthClient.authenticate();
        this.setState({authenticated: res});
    }

    render() {
        const css = config.get('globalCSS');

        return <Router>
            {css && <style>
                {css}
            </style>}
            {config.get('displayServicesMenu') && <DashboardMenu
                dashboardBaseUrl={config.get('dashboardBaseUrl')}
            />}
            <Switch>
                <Route path="/auth/:provider" component={props => {
                    return <OAuthRedirect
                        {...props}
                        oauthClient={oauthClient}
                        successHandler={(history) => {
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
                {!config.get('disableIndexPage') && <Route path="/" exact component={PublicationIndex} />}
                <Route path="/embed/:asset" exact render={({match: {params}}) => <EmbeddedAsset
                    id={params.asset}
                    authenticated={this.state.authenticated}
                />}/>
                <Route path="/:publication" exact render={props => <PublicationRoute
                    {...props}
                    authenticated={this.state.authenticated}
                />}/>
                <Route path="/:publication/:asset" exact render={props => <AssetRoute
                    {...props}
                    authenticated={this.state.authenticated}
                />}/>
                <Route path="/" exact render={() => <ErrorPage
                    title={'Not found'}
                    code={404}
                />}/>
            </Switch>
        </Router>
    }
}

export default App;
