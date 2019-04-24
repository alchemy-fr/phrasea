import React, {Component} from 'react';
import {Route, Redirect} from "react-router-dom";
import auth from "../store/auth";

export default class PrivateRoute extends Component {
    isAuthenticated() {
        return auth.hasAccessToken();
    }

    render() {
        const {component: Component, ...rest} = this.props;

        return <Route {...rest} render={(props) => (
            this.isAuthenticated() === true
                ? <Component {...props} />
                : <Redirect to={{
                    pathname: '/login',
                    state: { from: props.location }
                }} />
        )} />;
    }
}
