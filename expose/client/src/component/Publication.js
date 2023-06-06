import React, {PureComponent} from 'react';
import config from '../lib/config';
import {PropTypes} from 'prop-types';
import {layouts} from "./layouts";
import ThemeEditorProxy from "./themes/ThemeEditorProxy";
import {securityMethods} from "./security/methods";
import Layout from "./Layout";
import PublicationNavigation from "./PublicationNavigation";
import {isTermsAccepted, setAcceptedTerms} from "../lib/credential";
import Urls from "./layouts/shared-components/Urls";
import Copyright from "./layouts/shared-components/Copyright";
import TermsModal from "./layouts/shared-components/TermsModal";
import {oauthClient} from "../lib/oauth";
import ErrorPage from "./ErrorPage";
import {loadPublication} from "./api";
import PublicationSecurityProxy from "./security/PublicationSecurityProxy";


class Publication extends PureComponent {
    static propTypes = {
        id: PropTypes.string.isRequired,
        assetId: PropTypes.string,
        authenticated: PropTypes.object,
    };

    state = {
        data: null,
        error: null,
    };

    static getDerivedStateFromProps(props, state) {
        if (props.id !== state.propsId) {
            return {
                propsId: props.id,
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

        oauthClient.registerListener('logout', this.onLogout);
    }

    componentWillUnmount() {
        this.timeout && clearTimeout(this.timeout);
        oauthClient.unregisterListener('logout', this.onLogout);
    }

    onLogout = () => {
        this.setState({
            data: null,
            error: null,
        }, this.load);
    }

    async load() {
        const {id} = this.props;

        try {
            const currentData = this.state.data;
            if (currentData && (currentData.slug ? id !== currentData.slug : id !== currentData.id)) {
                this.setState({data: null, error: null});
            }

            const res = await loadPublication(id);

            if (res.slug && res.slug !== id) {
                document.location.href = document.location.href.replace(id, res.slug);
            }

            this.setState({data: res, error: null});

            this.timeout && clearTimeout(this.timeout);

            const ttl = config.get('requestSignatureTtl');

            if (!ttl) {
                throw new Error(`Missing requestSignatureTtl`);
            }
            this.timeout = setTimeout(() => {
                this.load();
            }, ttl * 1000 - 2000);
        } catch (err) {
            if (err.response && 200 !== err.response.statusCode) {
                this.setState({error: err.response.statusCode})
            }
        }
    }

    render() {
        const {data, error} = this.state;

        if (error) {
            return this.renderError();
        }

        return <>
            {data && data.cssLink ? <link rel="stylesheet" type="text/css" href={data.cssLink}/> : ''}
            <ThemeEditorProxy
                data={data}
                render={this.renderLayout}
            />
        </>
    }

    renderError() {
        const {error} = this.state;
        let err;
        if ([401, 403].includes(error)) {
            err = <ErrorPage
                title={'Forbidden'}
                code={error}
            />
        } else if (404 === error) {
            err = <ErrorPage
                title={'Not found'}
                code={error}
            />
        }

        return <Layout
            authenticated={this.props.authenticated}
        >
            {err}
        </Layout>
    }

    renderLayout = (data) => {
        return <Layout
            authenticated={this.props.authenticated}
            menu={<>
                {data && <PublicationNavigation
                    publication={data}
                />}
                <div className="p-3">
                    {data && data.urls ? <Urls urls={data.urls}/> : ''}
                    {data ? <Copyright text={data.copyrightText}/> : ''}
                    {data && data.editor ? data.editor : ''}
                </div>
            </>}
        >
            <PublicationSecurityProxy
                publication={data || undefined}
                reload={this.onAuthorizationChange}
                logPublicationView={true}
            >
                {data && data.authorized ? this.renderContent(data) : ''}
            </PublicationSecurityProxy>
        </Layout>
    }

    acceptTerms = () => {
        setAcceptedTerms('p_' + this.state.data.id);
        this.forceUpdate();
    }

    renderContent(data) {
        if (data && data.terms.enabled && !isTermsAccepted('p_' + data.id)) {
            return this.renderTerms();
        }

        if (!layouts[data.layout]) {
            throw new Error(`Unsupported layout ${data.layout}`);
        }
        const Layout = layouts[data.layout];

        return <Layout
            data={data}
            assetId={this.props.assetId}
            options={data.layoutOptions}
            mapOptions={data.mapOptions}
        />
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
