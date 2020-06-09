import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";
import {oauthClient} from "../lib/oauth";
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";
import config from "../lib/config";

class Layout extends PureComponent {
    static propTypes = {
        menu: PropTypes.node,
    };

    constructor(props) {
        super(props);

        this.state = {
            displayMenu: window.innerWidth >= 992,
            authenticated: null,
        }
    }

    componentDidMount() {
        this.init();

        oauthClient.registerListener('login', this.onLogin);
    }

    componentWillUnmount() {
        oauthClient.unregisterListener('login', this.onLogin);
    }

    init = async () => {
        if (oauthClient.getAccessToken()) {
            if (!this.state.authenticated) {
                this.authenticate();
            }
        }
    }

    onLogin = async () => {
        this.authenticate();
    }

    render() {
        return <div className={'wrapper d-flex align-items-stretch'}>
            <nav id="sidebar" className={!this.state.displayMenu ? 'hidden': ''}>
                <div className="custom-menu">
                    <button
                        type="button"
                        onClick={() => this.setState({displayMenu: !this.state.displayMenu})}
                            className="btn btn-primary"
                    >
                        <i className="fa fa-bars"></i>
                        <span className="sr-only">Toggle Menu</span>
                    </button>
                </div>
                <div className="p-4 pt-5">
                    {this.renderAuthenticated()}
                    <h1><Link to={'/'} className="logo">Expose</Link></h1>

                    {this.props.menu}
                </div>
            </nav>
            <div className="main-content p-4 p-md-5 pt-5">
                {this.props.children}
            </div>
        </div>
    }

    logout = () => {
        oauthClient.logout();

        this.setState({
            data: null,
            authorization: null,
            authenticated: null,
        }, () => {
            this.init();
        });
    }

    async authenticate() {
        const res = await oauthClient.authenticate(`${config.getApiBaseUrl()}/me`);
        this.setState({authenticated: res});
    }

    renderAuthenticated() {
        const {authenticated} = this.state;

        if (null === authenticated) {
            return '';
        }

        if (!authenticated) {
            return <FullPageLoader />
        }

        return <div className={'authenticated-user'}>
            Authenticated as {authenticated.username}
            <br/>
            <button
                onClick={this.logout}
                className={'btn btn-sm'}
            >
                Logout
            </button>
        </div>
    }
}

export default Layout;
