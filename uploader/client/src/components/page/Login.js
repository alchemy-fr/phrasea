import React, {Component} from 'react';
import {Redirect} from "react-router-dom";
import config from '../../config';
import {oauthClient} from "../../oauth";

export default class Login extends Component {
    state = {
        redirectToReferrer: false,
    };

    render() {
        const {redirectToReferrer} = this.state;
        const {from} = this.props.location.state || {from: {pathname: '/'}};

        if (oauthClient.isAuthenticated() || redirectToReferrer === true) {
            return <Redirect to={from}/>
        }

        document.location.href = oauthClient.createAuthorizeUrl({
            connectTo: config.autoConnectIdP || undefined,
        });

        return '';
    }
}
