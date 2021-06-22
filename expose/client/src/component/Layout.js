import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";
import {oauthClient} from "../lib/oauth";
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";
import config from '../lib/config';

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
                <div>
                    {this.renderAuthenticated()}
                    <div className="p-3">
                        <h1><Link to={'/'} className="logo">Expose</Link></h1>
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
    }

    renderAuthenticated() {
        const {authenticated} = this.props;

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
