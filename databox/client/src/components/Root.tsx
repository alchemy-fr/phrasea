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
import {UserContext} from "./Security/UserContext";

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

type State = {
    user?: User,
    authenticating: boolean,
};

export default class Root extends PureComponent<{}, State> {
    state: State = {
        authenticating: false,
    }

    componentDidMount() {
        oauthClient.registerListener('authentication', (evt: { user: User }) => {
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
            } else {
                document.location.reload();
            }
        });

        this.authenticate();
    }

    authenticate = (): void => {
        if (!oauthClient.hasAccessToken()) {
            return;
        }

        this.setState({authenticating: true}, () => {
            authenticate().then(() => {
                this.setState({authenticating: false});
            }, (e: any) => {
                console.log('e', e);
                oauthClient.logout();
            });
        });
    }

    render() {
        const authenticated = oauthClient.isAuthenticated();

        return <UserContext.Provider value={{
            user: this.state.user,
        }}>
            {this.state.authenticating
                ? <FullPageLoader/>
                : <Router>
                    <PrivateRoute path={'/workspaces/:id/edit'} component={createRouteComponent(EditWorkspace)}
                                  authenticated={authenticated}/>
                    <Route path={'/'} exact={true} component={App}/>
                    <Route path={`/auth`} component={OAuthRedirect}/>
                    <Route path="/login" exact component={Login}/>
                </Router>}
        </UserContext.Provider>
    }
}
