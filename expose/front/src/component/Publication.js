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
import {getAuthorization, setAuthorization} from "../lib/credential";

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
                authorization: getAuthorization(props.id),
                propsId: props.id,
                data: null,
            }
        }

        return null;
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (
            prevState.authorization !== this.state.authorization
            || prevProps.id !== this.props.id
        ) {
            this.load();
        }
    }

    onAuthorizationChange = (authorization) => {
        let {id, slug} = this.state.data;
        setAuthorization(id, authorization);
        if (slug) {
            setAuthorization(slug, authorization);
        }

        console.log('id', id);
        this.setState({authorization}, () => {
            this.load();
        });
    };

    componentDidMount() {
        this.load();
    }

    async load() {
        const {id} = this.props;
        const {authorization} = this.state;
        const options = {};
        const authHeader = authorization || getAuthorization(id);

        if (authHeader) {
            options.headers = {'Authorization': authHeader};
        }

        const req = apiClient.get(`${config.getApiBaseUrl()}/publications/${id}`, {}, options);
        const res = await req;
        this.setState({data: res});
    }

    render() {
        const {data} = this.state;

        return <Layout
            menu={<PublicationNavigation
                currentTitle={data ? data.title : 'Loading...'}
                children={data && data.children ? data.children : []}
                parent={data ? data.parent : null}
            />}
        >
            {this.renderContent()}
        </Layout>
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
