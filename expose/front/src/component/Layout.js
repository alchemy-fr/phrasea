import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";

class Layout extends PureComponent {
    static propTypes = {
        menu: PropTypes.node,
    };

    state = {
        displayMenu: true,
    };

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
                    <h1><Link to={'/'} className="logo">Expose</Link></h1>

                    {this.props.menu}
                </div>
            </nav>
            <div className="main-content p-4 p-md-5 pt-5">
                {this.props.children}
            </div>
        </div>
    }
}

export default Layout;
