import React, {PureComponent} from 'react';
import config from '../lib/config';
import apiClient from '../lib/apiClient';
import {PropTypes} from 'prop-types';
import FullPageLoader from "./FullPageLoader";
import {layouts} from "./layouts";
import ThemeEditorProxy from "./themes/ThemeEditorProxy";
import Cookies from 'universal-cookie';
import {securityMethods} from "./security/methods";

const cookies = new Cookies();

function getAuthCookieName(publicationId) {
    return `auth_${publicationId}`;
}

function getAuthorizationFromCookies(publicationId) {
    return cookies.get(getAuthCookieName(publicationId));
}

class Publication extends PureComponent {
    static propTypes = {
        id: PropTypes.string.isRequired,
        assetId: PropTypes.string,
    };

    state = {
        data: null,
        authorization: null,
    };

    static getDerivedStateFromProps(props, state) {
        if (props.id !== state.propsId) {
            return {
                propsId: props.id,
                data: null,
            }
        }

        return null;
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (prevState.authorization !== this.state.authorization) {
            this.load();
        }
    }

    onAuthorizationChange = (authorization) => {
        let {id, slug} = this.state.data;
        cookies.set(getAuthCookieName(id), authorization, {path: '/'});
        if (slug) {
            cookies.set(getAuthCookieName(slug), authorization, {path: '/'});
        }

        this.setState({authorization}, () => {
            this.load();
        });
    };

    componentDidMount() {
        this.load();
    }

    load() {
        const {id} = this.props;
        const {authorization} = this.state;
        const options = {
            withCredentials: true
        };
        const authHeader = authorization || getAuthorizationFromCookies(id);

        if (authHeader) {
            options.headers = {'Authorization': authHeader};
        }

        const req = apiClient.get(`${config.getApiBaseUrl()}/publications/${id}`, {}, options);

        req.then((res) => {
            this.setState({data: res});
        });
    }

    render() {
        const {data} = this.state;

        if (null === data) {
            return <FullPageLoader/>;
        }

        if (!data.authorized) {
            return this.renderSecurityAccess();
        }

        return <div className={`publication`}>
            <ThemeEditorProxy
                data={data}
                render={data => {

                    if (!layouts[data.layout]) {
                        throw new Error(`Unsupported layout ${data.layout}`);
                    }

                    const Layout = layouts[data.layout];
                    return <Layout
                        data={data}
                        assetId={this.props.assetId}
                    />
                }}
            />
        </div>
    }

    renderSecurityAccess() {
        const {data} = this.state;

        if (securityMethods[data.securityMethod]) {
            return React.createElement(securityMethods[data.securityMethod], {
                error: data.authorizationError,
                onAuthorization: this.onAuthorizationChange,
            });
        }

        return <div>
            Sorry! You cannot access this publication.
        </div>
    }
}

export default Publication;
