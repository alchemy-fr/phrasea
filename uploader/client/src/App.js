import React, {Component} from 'react';
import './scss/App.scss';
import Upload from "./components/page/Upload";
import {slide as Menu} from 'react-burger-menu';
import {BrowserRouter as Router, Route, Link} from "react-router-dom";
import Login from "./components/page/Login";
import DevSettings from "./components/page/DevSettings";
import config from './config';
import PrivateRoute from "./components/PrivateRoute";
import UserInfo from "./components/UserInfo";
import FormEditor from "./components/page/FormEditor";
import ResetPassword from "./components/page/ResetPassword";
import Download from "./components/page/Download";
import TargetDataEditor from "./components/page/TargetDataEditor";
import Languages from "./components/Languages";
import {withTranslation} from 'react-i18next';
import {oauthClient, OAuthRedirect} from "./oauth";
import AuthError from "./components/page/AuthError";
import SelectTarget from "./components/page/SelectTarget";
import {DashboardMenu} from "react-ps";
import FullPageLoader from "./components/FullPageLoader";

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
        oauthClient.registerListener('login', this.authenticate);

        oauthClient.registerListener('logout', () => {
            if (config.isDirectLoginForm()) {
                this.setState({
                    user: null,
                });
            }
        });
    }

    componentDidMount() {
        if (oauthClient.hasAccessToken()) {
            this.authenticate();
        }
    }

    authenticate = () => {
        return new Promise((resolve) => {
            this.setState({authenticating: true}, () => {
                oauthClient.authenticate(config.getUploadBaseURL()+'/me').then(() => {
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

    logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout?r=${encodeURIComponent(document.location.origin)}`;
        } else {
            this.closeMenu();
        }
    }

    render() {
        const {user} = this.state;
        const perms = user && user.permissions;

        return <Router>
            {config.get('displayServicesMenu') && <DashboardMenu
                dashboardBaseUrl={config.get('dashboardBaseUrl')}
            />}
            {this.state.authenticating ? <FullPageLoader/> : ''}
            <Route path={`/auth`} component={OAuthRedirect}/>
            <Menu
                pageWrapId="page-wrap"
                isOpen={this.state.menuOpen}
                onStateChange={(state) => this.handleStateChange(state)}
            >
                {this.state.user ? <UserInfo
                    email={this.state.user.email}
                /> : ''}
                <Link onClick={() => this.closeMenu()} to="/" className="menu-item">Home</Link>
                {perms && perms.form_schema ?
                    <Link onClick={() => this.closeMenu()} to="/form-editor">Form editor</Link> : ''}
                {perms && perms.target_data ?
                    <Link onClick={() => this.closeMenu()} to="/target-data-editor">Target data editor</Link> : ''}
                {config.devModeEnabled() ?
                    <Link onClick={() => this.closeMenu()} to="/dev-settings">DEV Settings</Link>
                    : ''}
                {oauthClient.isAuthenticated() ?
                    <a onClick={this.logout} href={'javascript:void(0)'}>Logout</a>
                    : ''}
                <Languages/>
            </Menu>
            <div id="page-wrap">
                <PrivateRoute path="/" exact component={SelectTarget}/>
                <PrivateRoute path="/upload/:id" exact component={Upload}/>
                <PrivateRoute path="/download/:id" exact component={Download}/>
                <Route path="/login" exact component={Login}/>
                <Route path="/forgot-password" exact component={ResetPassword}/>
                <Route path="/auth-error" exact component={AuthError}/>
                {perms && perms.form_schema ? <PrivateRoute path="/form-editor" exact component={FormEditor}/> : ''}
                {perms && perms.target_data ?
                    <PrivateRoute path="/target-data-editor" exact component={TargetDataEditor}/> : ''}
                {config.devModeEnabled() ?
                    <Route path="/dev-settings" exact component={DevSettings}/>
                    : ''}
            </div>
        </Router>
    }
}

export default withTranslation()(App);
