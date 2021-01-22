import React, {PureComponent} from 'react';
import {BrowserRouter as Router, Route} from "react-router-dom";
import {oauthClient, OAuthRedirect} from "../oauth";
import PrivateRoute from "./PrivateRoute";
import App from "./App";
import Login from "./Security/Login";
import {authenticate} from "../auth";
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";
import config from "../config";

export default class Root extends PureComponent {
    state = {
        user: null,
        authenticating: false,
    }
    constructor(props: {}) {
        super(props);

        oauthClient.registerListener('authentication', (evt: {user: object}) => {
            this.setState({
                user: evt.user,
            });
        });
        oauthClient.registerListener('login', authenticate);

        oauthClient.registerListener('logout', () => {
            if (config.isDirectLoginForm()) {
                this.setState({
                    user: null,
                });
            }
        });
    }

    componentDidMount() {
        this.authenticate();
    }

    authenticate = (): Promise<void> => {
        return new Promise<void>((resolve: () => void) => {
            this.setState({authenticating: true}, () => {
                authenticate().then(() => {
                    this.setState({authenticating: false}, resolve);
                });
            });
        });
    }

    render() {
        return <>
            {this.state.authenticating ? <FullPageLoader/> : ''}
            <Router>
                <PrivateRoute path={'/'} exact={true} component={App}/>
                <Route path={`/auth`} component={OAuthRedirect}/>
                <Route path="/login" exact component={Login}/>
            </Router>
        </>
    }
}
