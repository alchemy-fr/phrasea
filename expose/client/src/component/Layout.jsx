import React, { PureComponent } from 'react'
// import { PropTypes } from 'prop-types'
import { Link } from 'react-router-dom'
import config from '../lib/config'
import { Logo } from './Logo'
import { Trans } from 'react-i18next'
import { keycloakClient } from '../lib/api-client'

class Layout extends PureComponent {
    // static propTypes = {
    //     menu: PropTypes.node,
    //     username: PropTypes.string,
    // }

    constructor(props) {
        super(props)

        this.state = {
            displayMenu: config.sidebarDefaultOpen,
        }
    }

    render() {
        const { menu, children } = this.props
        const { displayMenu } = this.state

        return (
            <div className={'wrapper d-flex align-items-stretch'}>
                <nav id="sidebar" className={!displayMenu ? 'hidden' : ''}>
                    <div className="custom-menu">
                        <button
                            type="button"
                            onClick={() =>
                                this.setState((p) => ({
                                    displayMenu: !p.displayMenu,
                                }))
                            }
                            className="btn btn-primary"
                        >
                            <i className="fa fa-bars" />
                            <span className="sr-only">
                                <Trans i18nKey="menu.toggle">Toggle Menu</Trans>
                            </span>
                        </button>
                    </div>
                    <div>
                        {this.renderAuthenticated()}
                        <div className="p-3">
                            <h1>
                                {!config.disableIndexPage ? (
                                    <Link to={'/'} className="logo">
                                        <Logo />
                                    </Link>
                                ) : (
                                    <Logo />
                                )}
                            </h1>
                        </div>

                        {menu}
                    </div>
                </nav>
                <div
                    className={`main-content${
                        displayMenu ? ' menu-open' : ''
                    } p-4 p-md-5 pt-5`}
                >
                    {children}
                </div>
            </div>
        )
    }

    logout = () => {
        keycloakClient.logout()
    }

    renderAuthenticated() {
        const { username } = this.props

        if (!username) {
            return ''
        }

        return (
            <div className={'authenticated-user'}>
                Authenticated as {username}
                <br />
                <button
                    onClick={this.logout}
                    className={'btn btn-sm btn-logout'}
                >
                    Logout
                </button>
            </div>
        )
    }
}

export default Layout
