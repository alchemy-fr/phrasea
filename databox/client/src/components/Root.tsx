import React, {PureComponent} from 'react';
import {BrowserRouter as Router, Route, RouteComponentProps} from "react-router-dom";
import {oauthClient, OAuthRedirect} from "../oauth";
import PrivateRoute from "./PrivateRoute";
import App from "./App";
import Login from "./Security/Login";
import {authenticate} from "../auth";
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";
import config from "../config";
import apiClient from "../api/api-client";
import EditWorkspace from "./Media/Workspace/EditWorkspace";
import {User} from "../types";

type IdRouteProps = RouteComponentProps<{
    id: string,
}, {}, {
    attrs: object,
}>;

const mapProps = (props: IdRouteProps) => ({
    ...props,
    id: props.match.params.id,
    attrs: props.location.state ? props.location.state.attrs : null,
});

function createRouteComponent(C: React.ComponentType<RouteComponentProps<any>> | React.ComponentType<any>) {
    return (props: IdRouteProps) => <C {...mapProps(props)} />
}

export default class Root extends PureComponent<{}, {
    user?: User,
    authenticating: boolean,
}> {
    state = {
        authenticating: true,
    }

    componentDidMount() {
        oauthClient.registerListener('authentication', (evt: {user: User}) => {
            apiClient.defaults.headers.common['Authorization'] = `Bearer ${oauthClient.getAccessToken()}`;
            this.setState({
                user: evt.user,
                authenticating: false,
            });
        });
        oauthClient.registerListener('login', authenticate);

        oauthClient.registerListener('logout', () => {
            if (config.isDirectLoginForm()) {
                this.setState({
                    user: undefined,
                });
            }
        });

        this.authenticate();
    }

    authenticate = (): void => {
        if (!oauthClient.hasAccessToken()) {
            return;
        }
        authenticate().then(() => {
            this.setState({authenticating: false});
        });
    }

    render() {
        const authenticated = oauthClient.isAuthenticated();
        console.log('render Root', this.state.authenticating, authenticated);

        return <>
            {this.state.authenticating ? <FullPageLoader/> : ''}
            <Router>
                <PrivateRoute path={'/workspaces/:id/edit'} component={createRouteComponent(EditWorkspace)} authenticated={authenticated}/>
                <PrivateRoute path={'/'} exact={true} component={App} authenticated={authenticated}/>
                <Route path={`/auth`} component={OAuthRedirect}/>
                <Route path="/login" exact component={Login}/>
            </Router>
        </>
    }
}
