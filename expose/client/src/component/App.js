import React, {PureComponent} from 'react';
import {Route, BrowserRouter as Router, Switch} from "react-router-dom";
import PublicationRoute from "./routes/PublicationRoute";
import PublicationIndex from "./index/PublicationIndex";
import AssetRoute from "./routes/AssetRoute";
import {getAuthRedirect, oauthClient, unsetAuthRedirect} from "../lib/oauth";
import {OAuthRedirect, ServicesMenu} from '@alchemy-fr/phraseanet-react-components';
import config from "../lib/config";

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

        const res = await oauthClient.authenticate(`${config.getApiBaseUrl()}/me`);
        this.setState({authenticated: res});
    }

    render() {
        const css = config.get('globalCSS');

        return <Router>
            {css && <style>
                {css}
            </style>}
            {config.get('displayServicesMenu') ? <ServicesMenu
                dashboardBaseUrl={`${config.get('dashboardBaseUrl')}/menu.html`}
            /> : ''}
            <Switch>
                <Route path="/auth/:provider" component={props => {
                    return <OAuthRedirect
                        {...props}
                        oauthClient={oauthClient}
                        successHandler={(history) => {
                            history.replace(getAuthRedirect());
                            unsetAuthRedirect();
                        }}
                    />
                }}/>
                <Route path="/" exact component={PublicationIndex} />
                <Route path="/:publication" exact render={props => <PublicationRoute
                    {...props}
                    authenticated={this.state.authenticated}
                />}/>
                <Route path="/:publication/:asset" exact component={AssetRoute}/>
                <Route path="/:publication/:asset/:subdef" exact component={AssetRoute}/>
            </Switch>
        </Router>
    }
}

export default App;
