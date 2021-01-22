import React, {Component} from 'react';
import {Route, Redirect} from "react-router-dom";
import {oauthClient} from "../oauth";

type Props = {
    component: React.ComponentType<any>;
    path: string;
    exact?: boolean;
};

export default class PrivateRoute extends Component<Props, any> {
    render() {
        const {component: C, ...rest} = this.props;

        return <Route {...rest} render={(props) => (
            oauthClient.isAuthenticated() === true
                ? <C {...props} />
                : (oauthClient.hasAccessToken() ? '' : <Redirect to={{
                    pathname: '/login',
                    state: { from: props.location }
                }} />)
        )} />;
    }
}
