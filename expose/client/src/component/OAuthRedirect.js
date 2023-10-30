import React, {PureComponent} from 'react';
import qs from 'querystring';
import PropTypes from "prop-types";
import FullPageLoader from "./FullPageLoader";

export default class OAuthRedirect extends PureComponent {
    static propTypes = {
        oauthClient: PropTypes.object.isRequired,
        history: PropTypes.object.isRequired,
        location: PropTypes.object.isRequired,
        successUri: PropTypes.string,
        errorUri: PropTypes.string,
        successHandler: PropTypes.func,
        errorHandler: PropTypes.func,
    };

    handleSuccess = () => {
        const {
            history,
            successUri,
            successHandler,
        } = this.props;

        if (successHandler) {
            return successHandler(history);
        }

        history.push(successUri || '/');
    };

    handleError = (e) => {
        const {
            history,
            errorUri,
            errorHandler,
        } = this.props;

        if (errorHandler) {
            return errorHandler(e, history);
        }

        console.error(e);
        history.push(errorUri || '/');
    };

    componentDidMount() {
        const {oauthClient} = this.props;

        if (oauthClient.getAccessToken()) {
            this.handleSuccess();
        }

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
        return <FullPageLoader/>;
    }
}
