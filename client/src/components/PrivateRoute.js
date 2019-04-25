import React, {Component} from 'react';
import {Route, Redirect} from "react-router-dom";
import auth from "../store/auth";

export default class PrivateRoute extends Component {
    render() {
        const {component: Component, ...rest} = this.props;

        return <Route {...rest} render={(props) => (
            auth.isAuthenticated() === true
                ? <Component {...props} />
                : <Redirect to={{
                    pathname: '/login',
                    state: { from: props.location }
                }} />
        )} />;
    }
}
