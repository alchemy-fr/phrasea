import React, {Component} from 'react';
import {Route, Redirect, RouteComponentProps} from "react-router-dom";
import {oauthClient} from "../oauth";

type Props = {
    component: React.ComponentType<RouteComponentProps<any>> | React.ComponentType<any>;
    path: string;
    exact?: boolean;
    authenticated: boolean;
};

export default class PrivateRoute extends Component<Props, any> {
    render() {
        const {component: C, authenticated, ...rest} = this.props;

        return <Route {...rest} render={(props) => (
            authenticated
                ? <C {...props} />
                : (oauthClient.hasAccessToken() ? '' : <Redirect to={{
                    pathname: '/login',
                    state: { from: props.location }
                }} />)
        )} />;
    }
}
