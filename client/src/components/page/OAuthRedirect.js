import React, {Component} from 'react';
import qs from 'querystring';
import auth from "../../auth";

export default class OAuthRedirect extends Component {
    handleSuccess = () => {
        this.props.history.push('/');
    };

    handleError = (e) => {
        console.error(e);
        alert(e);
        this.props.history.push('/');
    };

    componentDidMount() {
        auth.getAccessTokenFromAuthCode(
            this.getCode(),
            window.location.href.split('?')[0],
            this.handleSuccess,
            this.handleError
        );
    }

    getCode() {
        return qs.parse(this.props.location.search.substring(1)).code;
    }

    getProviderKey() {
        return this.props.match.params.provider;
    }

    render() {
        // TODO
        return '';
    }
}
