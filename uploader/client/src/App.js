import React, {Component} from 'react';
import './scss/App.scss';
import Upload from "./components/page/Upload";
import {slide as Menu} from 'react-burger-menu';
import {BrowserRouter as Router, Route, Link} from "react-router-dom";
import Settings from "./components/page/Settings";
import Login from "./components/page/Login";
import About from "./components/page/About";
import DevSettings from "./components/page/DevSettings";
import config from './config';
import {authenticate} from './auth';
import PrivateRoute from "./components/PrivateRoute";
import UserInfo from "./components/UserInfo";
import FormEditor from "./components/page/FormEditor";
import ResetPassword from "./components/page/ResetPassword";
import Download from "./components/page/Download";
import BulkDataEditor from "./components/page/BulkDataEditor";
import Languages from "./components/Languages";
import {withTranslation} from 'react-i18next';
import {oauthClient, OAuthRedirect} from "./oauth";
import {FullPageLoader, ServicesMenu} from "@alchemy-fr/phraseanet-react-components";

class App extends Component {
    state = {
        menuOpen: false,
        user: null,
        authenticating: false,
    };

    constructor(props) {
        super(props);

        oauthClient.registerListener('authentication', (evt) => {
            this.setState({
                user: evt.user,
            });
        });
        oauthClient.registerListener('login', authenticate);
        oauthClient.registerListener('logout', () => {
            this.setState({
                user: null,
            });
        });
    }

    componentDidMount() {
        this.authenticate();
    }

    authenticate = () => {
        return new Promise((resolve) => {
            this.setState({authenticating: true}, () => {
                authenticate().then(() => {
                    this.setState({authenticating: false}, resolve);
                });
            });
        });
    }

    handleStateChange(state) {
        this.setState({menuOpen: state.isOpen})
    }

    closeMenu() {
        this.setState({menuOpen: false})
    }

    logout() {
        oauthClient.logout();
    }

    render() {
        const {user} = this.state;
        const perms = user && user.permissions;

        return <Router>
            {config.get('displayServicesMenu') ? <ServicesMenu
                dashboardBaseUrl={`${config.get('dashboardBaseUrl')}/menu.html`}
            /> : ''}
            {this.state.authenticating ? <FullPageLoader/> : ''}
            <Route path="/auth" component={OAuthRedirect}/>
            <Menu
                pageWrapId="page-wrap"
                isOpen={this.state.menuOpen}
                onStateChange={(state) => this.handleStateChange(state)}
            >
                {this.state.user ? <UserInfo
                    email={this.state.user.email}
                /> : ''}
                <Link onClick={() => this.closeMenu()} to="/" className="menu-item">Home</Link>
                <Link onClick={() => this.closeMenu()} to="/about">About</Link>
                <Link onClick={() => this.closeMenu()} to="/settings">Settings</Link>
                {perms && perms.form_schema ?
                    <Link onClick={() => this.closeMenu()} to="/form-editor">Form editor</Link> : ''}
                {perms && perms.bulk_data ?
                    <Link onClick={() => this.closeMenu()} to="/bulk-data-editor">Bulk data editor</Link> : ''}
                {config.devModeEnabled() ?
                    <Link onClick={() => this.closeMenu()} to="/dev-settings">DEV Settings</Link>
                    : ''}
                {oauthClient.isAuthenticated() ?
                    <Link onClick={() => {
                        this.logout();
                        this.closeMenu()
                    }} to={'#'}>Logout</Link>
                    : ''}
                <Languages/>
            </Menu>
            <div id="page-wrap">
                <PrivateRoute path="/" exact component={Upload}/>
                <PrivateRoute path="/download" exact component={Download}/>
                <Route path="/login" exact component={Login}/>
                <Route path="/forgot-password" exact component={ResetPassword}/>
                <Route path="/about" exact component={About}/>
                <PrivateRoute path="/settings" exact component={Settings}/>
                {perms && perms.form_schema ? <PrivateRoute path="/form-editor" exact component={FormEditor}/> : ''}
                {perms && perms.bulk_data ?
                    <PrivateRoute path="/bulk-data-editor" exact component={BulkDataEditor}/> : ''}
                {config.devModeEnabled() ?
                    <Route path="/dev-settings" exact component={DevSettings}/>
                    : ''}
            </div>
        </Router>
    }
}

export default withTranslation()(App);
