import React, {PureComponent} from 'react';
import config from '../lib/config';
import apiClient from '../lib/apiClient';
import {PropTypes} from 'prop-types';
import FullPageLoader from "./FullPageLoader";
import {layouts} from "./layouts";
import ThemeEditorProxy from "./themes/ThemeEditorProxy";
import {securityMethods} from "./security/methods";
import Layout from "./Layout";
import PublicationNavigation from "./PublicationNavigation";
import {getAccessToken, getPasswords, isTermsAccepted, setAcceptedTerms} from "../lib/credential";
import Urls from "./layouts/shared-components/Urls";
import Copyright from "./layouts/shared-components/Copyright";
import Cover from "./layouts/shared-components/Cover";

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

        const accessToken = getAccessToken();
        if (accessToken) {
            options.headers = {'Authorization': `Bearer ${accessToken}`};
        }

        const req = apiClient.get(`${config.getApiBaseUrl()}/publications/${id}`, {}, options);
        const res = await req;
        this.setState({data: res});
    }

    render() {
        const {data} = this.state;

        if (data && data.terms.enabled && !isTermsAccepted('p_'+data.id)) {
            return this.renderTerms();
        }

        return <>
            {data && data.cssLink ? <link rel="stylesheet" type="text/css" href={data.cssLink} /> : ''}
            <Layout
                menu={
                    <>
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

    acceptTerms = () => {
        setAcceptedTerms('p_'+this.state.data.id);
        this.forceUpdate();
    }

    renderTerms() {
        const {data} = this.state;
        const {text, url} = data.terms;

        return <div
            className={'container terms-invite'}
        >
            <p>
                {text ? text
                    : <>
                        Please read and accept the{' '}
                        <a href={url} target={'_blank'}>terms</a>
                </>}
            </p>
            <button
                className={'btn btn-primary'}
                onClick={this.acceptTerms}
            >Accepter</button>
        </div>
    }

    renderContent() {
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
        const {securityContainerId} = data;

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
