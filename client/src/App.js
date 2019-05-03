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
import config from './store/config';
import auth from './store/auth';
import PrivateRoute from "./components/PrivateRoute";
import UserInfo from "./components/UserInfo";

class App extends Component {
    constructor(props) {
        super(props);
        this.state = {
            menuOpen: false,
            user: null,
        };

        auth.registerListener('authentication', (evt) => {
            console.log('on auth', evt);
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
                    {config.devModeEnabled() ?
                        <Link onClick={() => this.closeMenu()} to="/dev-settings">DEV Settings</Link>
                        : ''}
                    {auth.isAuthenticated() ?
                        <Link onClick={() => {this.logout(); this.closeMenu()}} to={'#'}>Logout</Link>
                        : ''}

                </Menu>
                <div id="page-wrap">
                    <PrivateRoute path="/" exact component={Upload}/>
                    <Route path="/login" exact component={Login}/>
                    <Route path="/about" exact component={About}/>
                    <PrivateRoute path="/settings" exact component={Settings}/>
                    {config.devModeEnabled() ?
                        <Route path="/dev-settings" exact component={DevSettings}/>
                        : ''}
                </div>
            </Router>
        );
    }
}

export default App;
