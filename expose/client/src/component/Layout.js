import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";
import {oauthClient} from "../lib/oauth";
import config from '../lib/config';
import {Logo} from "./Logo";
import {Trans} from "react-i18next";
import FullPageLoader from "./FullPageLoader";

class Layout extends PureComponent {
    static propTypes = {
        menu: PropTypes.node,
        authenticated: PropTypes.object,
    };

    constructor(props) {
        super(props);

        this.state = {
            displayMenu: config.get('sidebarDefaultOpen'),
        }
    }

    render() {
        return <div className={'wrapper d-flex align-items-stretch'}>
            <nav id="sidebar" className={!this.state.displayMenu ? 'hidden' : ''}>
                <div className="custom-menu">
                    <button
                        type="button"
                        onClick={() => this.setState({displayMenu: !this.state.displayMenu})}
                        className="btn btn-primary"
                    >
                        <i className="fa fa-bars"/>
                        <span className="sr-only">
                            <Trans i18nKey="menu.toggle">Toggle Menu</Trans>
                        </span>
                    </button>
                </div>
                <div>
                    {this.renderAuthenticated()}
                    <div className="p-3">
                        <h1>
                            {!config.get('disableIndexPage') ? <Link to={'/'} className="logo">
                                <Logo/>
                            </Link> : <Logo/>}
                        </h1>
                    </div>

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
        document.location.href = `${config.getAuthBaseUrl()}/security/logout?r=${encodeURIComponent(document.location.origin)}`;
    }

    renderAuthenticated() {
        const {authenticated} = this.props;

        if (null === authenticated) {
            return '';
        }

        if (!authenticated) {
            return <FullPageLoader/>
        }

        return <div className={'authenticated-user'}>
            Authenticated as {authenticated.username}
            <br/>
            <button
                onClick={this.logout}
                className={'btn btn-sm btn-logout'}
            >
                Logout
            </button>
        </div>
    }
}

export default Layout;
