import React, {PureComponent} from "react";
import config from "./config";
import {OAuthClient} from "react-ps";
import qs from 'querystring';
import PropTypes from "prop-types";
import FullPageLoader from "./components/FullPageLoader";

export const oauthClient = new OAuthClient({
    clientId: config.getClientId(),
    baseUrl: config.getAuthBaseUrl(),
});

// TODO should be remove when moving to axios
OAuthClient.prototype.isResponseValid = (err, res) => {
    if (err) {
        console.debug(err);
        console.debug(res);
        if (res && res.statusCode === 401) {
            this.logout();
        }
        return false;
    }

    return true;
}

export class OAuthRedirect extends PureComponent {
    static propTypes = {
        history: PropTypes.object.isRequired,
        location: PropTypes.object.isRequired,
        successUri: PropTypes.string,
        errorHandler: PropTypes.func,
    };

    handleSuccess = () => {
        const {
            history,
            successHandler,
        } = this.props;

        if (successHandler) {
            return successHandler(history);
        }

        history.push('/');
    };

    handleError = (e) => {
        const {
            history,
            errorHandler,
        } = this.props;

        if (errorHandler) {
            return errorHandler(e, history);
        }

        console.error(e);
        alert(e);
        history.push('/auth-error');
    };

    componentDidMount() {
        oauthClient
            .getAccessTokenFromAuthCode(
                this.getCode(),
                window.location.href.split('?')[0]
            )
            .then(this.handleSuccess, this.handleError)
        ;
    }

    getCode() {
        return qs.parse(this.props.location.search.substring(1)).code;
    }

    render() {
        return <FullPageLoader/>
    }
}
