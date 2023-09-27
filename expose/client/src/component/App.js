import React, {PureComponent} from 'react';
import {BrowserRouter as Router, Route, Switch} from "react-router-dom";
import PublicationRoute from "./routes/PublicationRoute";
import PublicationIndex from "./index/PublicationIndex";
import AssetRoute from "./routes/AssetRoute";
import {getAuthRedirect, unsetAuthRedirect} from "../lib/oauth";
import config from "../lib/config";
import ErrorPage from "./ErrorPage";
import OAuthRedirect from "./OAuthRedirect";
import {DashboardMenu} from "react-ps";
import EmbeddedAsset from "./EmbeddedAsset";
import {oauthClient} from "../lib/api-client";

class App extends PureComponent {
    state = {}

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
        if (oauthClient.isAuthenticated()) {
            this.onLogin();
        }
    }

    onLogin = () => {
        this.setState({username: oauthClient.getUsername()});
    }

    onLogout = async () => {
        this.setState({username: false});
    }

    render() {
        const css = config.globalCSS;

        return <Router>
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
                    username={this.state.username}
                />}/>
                <Route path="/:publication/:asset" exact render={props => <AssetRoute
                    {...props}
                    username={this.state.username}
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

const OAuthR = props => {
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
};
