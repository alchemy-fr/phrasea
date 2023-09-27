import React, {Component} from 'react';
import './scss/App.scss';
import Upload from "./components/page/Upload";
import {slide as Menu} from 'react-burger-menu';
import {BrowserRouter as Router, Route, Link} from "react-router-dom";
import Login from "./components/page/Login";
import config from './config';
import PrivateRoute from "./components/PrivateRoute";
import UserInfo from "./components/UserInfo";
import FormEditor from "./components/page/FormEditor";
import Download from "./components/page/Download";
import TargetDataEditor from "./components/page/TargetDataEditor";
import Languages from "./components/Languages";
import {withTranslation} from 'react-i18next';
import {oauthClient, OAuthRedirect} from "./oauth";
import AuthError from "./components/page/AuthError";
import SelectTarget from "./components/page/SelectTarget";
import {DashboardMenu} from "react-ps";
import FullPageLoader from "./components/FullPageLoader";
import {authenticatedRequest} from "./lib/api";

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
    }

    componentDidMount() {
        if (oauthClient.isAuthenticated()) {
            this.authenticate();
        }
    }

    authenticate = async () => {
        this.setState({authenticating: true});
        await authenticatedRequest({
            url: '/me',
        });
        this.setState({authenticating: false});
    }

    handleStateChange(state) {
        this.setState({menuOpen: state.isOpen})
    }

    closeMenu() {
        this.setState({menuOpen: false})
    }

    logout = () => {
        oauthClient.logout();
    }

    render() {
        const {user} = this.state;
        const perms = user && user.permissions;

        return <Router>
            {config.displayServicesMenu && <DashboardMenu
                dashboardBaseUrl={config.dashboardBaseUrl}
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
                <Route path="/auth-error" exact component={AuthError}/>
                {perms && perms.form_schema ? <PrivateRoute path="/form-editor" exact component={FormEditor}/> : ''}
                {perms && perms.target_data ?
                    <PrivateRoute path="/target-data-editor" exact component={TargetDataEditor}/> : ''}
            </div>
        </Router>
    }
}

export default withTranslation()(App);
