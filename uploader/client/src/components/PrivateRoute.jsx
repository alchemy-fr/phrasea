import React, {Component} from 'react';
import {Route, Redirect} from 'react-router-dom';
import {oauthClient} from '../oauth';

export default class PrivateRoute extends Component {
    render() {
        const {component: Component, ...rest} = this.props;

        return (
            <Route
                {...rest}
                render={props =>
                    oauthClient.isAuthenticated() ? (
                        <Component {...props} />
                    ) : (
                        <Redirect
                            to={{
                                pathname: '/login',
                                state: {from: props.location},
                            }}
                        />
                    )
                }
            />
        );
    }
}
