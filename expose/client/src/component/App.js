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
        username: false,
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
