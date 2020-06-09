import React, {PureComponent} from 'react';
import config from '../lib/config';
import apiClient from '../lib/apiClient';
import {PropTypes} from 'prop-types';
import {layouts} from "./layouts";
import {FullPageLoader} from '@alchemy-fr/phraseanet-react-components';
import ThemeEditorProxy from "./themes/ThemeEditorProxy";
import {securityMethods} from "./security/methods";
import Layout from "./Layout";
import PublicationNavigation from "./PublicationNavigation";
import {getPasswords, isTermsAccepted, setAcceptedTerms} from "../lib/credential";
import Urls from "./layouts/shared-components/Urls";
import Copyright from "./layouts/shared-components/Copyright";
import Cover from "./layouts/shared-components/Cover";
import TermsModal from "./layouts/shared-components/TermsModal";
import {oauthClient} from "../lib/oauth";

class Publication extends PureComponent {
    static propTypes = {
        id: PropTypes.string.isRequired,
        assetId: PropTypes.string,
    };

    state = {
        data: null,
        authorization: null,
        authenticated: null,
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
        if (prevProps.id !== this.props.id) {
            this.load();
        }
    }

    onAuthorizationChange = () => {
        this.load();
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const {id} = this.props;
        const options = {};

        const passwords = getPasswords();
        if (passwords) {
            options.headers = {'X-Passwords': passwords};
        }

        const accessToken = oauthClient.getAccessToken();
        if (accessToken) {
            options.headers = {'Authorization': `Bearer ${accessToken}`};

            if (!this.state.authenticated) {
                this.authenticate();
            }
        }

        const req = apiClient.get(`${config.getApiBaseUrl()}/publications/${id}`, {}, options);
        const res = await req;
        this.setState({data: res});
    }

    async authenticate() {
        const res = await oauthClient.authenticate(`${config.getApiBaseUrl()}/me`);
        this.setState({authenticated: res});
    }

    render() {
        const {data} = this.state;

        return <>
            {data && data.cssLink ? <link rel="stylesheet" type="text/css" href={data.cssLink} /> : ''}
            <Layout
                menu={
                    <>
                        {this.renderAuthenticated()}
                        {data && data.cover ? <Cover
                            url={data.cover.thumbUrl}
                            alt={data.title}
                        /> : ''}
                        <PublicationNavigation
                            currentTitle={data ? data.title : 'Loading...'}
                            children={data && data.children ? data.children : []}
                            parent={data ? data.parent : null}
                        />
                        {data && data.urls ? <Urls urls={data.urls}/> : ''}
                        {data ? <Copyright text={data.copyrightText}/> : ''}
                    </>}
            >
                {this.renderContent()}
            </Layout>
        </>
    }

    logout = () => {
        oauthClient.logout();

        this.setState({
            data: null,
            authorization: null,
            authenticated: null,
        }, () => {
            this.load();
        });
    }

    renderAuthenticated() {
        const {authenticated} = this.state;

        if (null === authenticated) {
            return '';
        }

        if (!authenticated) {
            return <FullPageLoader />
        }

        return <div>
            Authenticated as {authenticated.username}
            <br/>
            <button
                onClick={this.logout}
                className={'btn btn-sm btn-danger'}
            >
                Logout
            </button>
        </div>
    }

    acceptTerms = () => {
        setAcceptedTerms('p_'+this.state.data.id);
        this.forceUpdate();
    }

    renderContent() {
        const {data} = this.state;

        if (null === data) {
            return <FullPageLoader/>;
        }

        if (!data.authorized) {
            return this.renderSecurityAccess();
        }

        if (data && data.terms.enabled && !isTermsAccepted('p_'+data.id)) {
            return this.renderTerms();
        }

        return <>
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
        </>
    }

    renderTerms() {
        const {data} = this.state;
        const {text, url} = data.terms;

        return <TermsModal
            title={'Publication'}
            onAccept={this.acceptTerms}
            text={text}
            url={url}
        />
    }

    renderSecurityAccess() {
        const {data} = this.state;
        const {securityContainerId} = data;

        if (data.authorizationError === 'not_allowed') {
            return <div>
                Sorry! You are not allowed to access this publication.
            </div>
        }

        if (securityMethods[data.securityMethod]) {
            return React.createElement(securityMethods[data.securityMethod], {
                error: data.authorizationError,
                onAuthorization: this.onAuthorizationChange,
                securityContainerId,
            });
        }

        return <div>
            Sorry! You cannot access this publication.
        </div>
    }
}

export default Publication;
