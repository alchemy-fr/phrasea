import React, {Component} from 'react';
import './scss/App.scss';
import './scss/Menu.scss';
import Upload from "./components/page/Upload";
import {slide as Menu} from 'react-burger-menu';
import {BrowserRouter as Router, Route, Link} from "react-router-dom";
import Settings from "./components/page/Settings";
import Login from "./components/page/Login";
import About from "./components/page/About";
import DevSettings from "./components/page/DevSettings";
import config from './config';
import auth from './auth';
import PrivateRoute from "./components/PrivateRoute";
import UserInfo from "./components/UserInfo";
import FormEditor from "./components/page/FormEditor";
import ResetPassword from "./components/page/ResetPassword";
import Download from "./components/page/Download";
import BulkDataEditor from "./components/page/BulkDataEditor";

class App extends Component {
    state = {
        menuOpen: false,
        user: null,
    };

    constructor(props) {
        super(props);

        auth.registerListener('authentication', (evt) => {
            this.setState({
                user: evt.user,
            });
        });
        auth.registerListener('login', (evt) => {
            auth.authenticate();
        });
        auth.registerListener('logout', (evt) => {
            this.setState({
                user: null,
            });
        });
    }

    componentDidMount() {
        auth.authenticate();
    }

    handleStateChange(state) {
        this.setState({menuOpen: state.isOpen})
    }

    closeMenu() {
        this.setState({menuOpen: false})
    }

    logout() {
        auth.logout();
    }

    render() {
        if (auth.hasAccessToken() && !auth.isAuthenticated()) {
            return 'Loading...';
        }

        const {user} = this.state;
        const isAdmin = user && user.is_admin;


        return (
            <Router>
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
                    {isAdmin ? <Link onClick={() => this.closeMenu()} to="/form-editor">Form editor</Link> : ''}
                    {isAdmin ? <Link onClick={() => this.closeMenu()} to="/bulk-data-editor">Bulk data editor</Link> : ''}
                    {config.devModeEnabled() ?
                        <Link onClick={() => this.closeMenu()} to="/dev-settings">DEV Settings</Link>
                        : ''}
                    {auth.isAuthenticated() ?
                        <Link onClick={() => {
                            this.logout();
                            this.closeMenu()
                        }} to={'#'}>Logout</Link>
                        : ''}

                </Menu>
                <div id="page-wrap">
                    <PrivateRoute path="/" exact component={Upload}/>
                    <PrivateRoute path="/download" exact component={Download}/>
                    <Route path="/login" exact component={Login}/>
                    <Route path="/forgot-password" exact component={ResetPassword}/>
                    <Route path="/about" exact component={About}/>
                    <PrivateRoute path="/settings" exact component={Settings}/>
                    {user && user.is_admin ? <React.Fragment>
                        <PrivateRoute path="/form-editor" exact component={FormEditor}/>
                        <PrivateRoute path="/bulk-data-editor" exact component={BulkDataEditor}/>
                    </React.Fragment> : ''}
                    {config.devModeEnabled() ?
                        <Route path="/dev-settings" exact component={DevSettings}/>
                        : ''}
                </div>
            </Router>
        );
    }
}

export default App;
