import React, {Component} from 'react';
import './scss/App.scss';
import './scss/Menu.scss';
import Upload from "./components/page/Upload";
import {slide as Menu} from 'react-burger-menu';
import {BrowserRouter as Router, Route, Link} from "react-router-dom";
import Settings from "./components/page/Settings";
import About from "./components/page/About";
import DevSettings from "./components/page/DevSettings";
import config from './store/config';

class App extends Component {
    constructor(props) {
        super(props);
        this.state = {
            menuOpen: false
        }
    }

    handleStateChange(state) {
        this.setState({menuOpen: state.isOpen})
    }

    closeMenu() {
        this.setState({menuOpen: false})
    }

    render() {
        return (
            <Router>
                <Menu
                    pageWrapId="page-wrap"
                    isOpen={this.state.menuOpen}
                    onStateChange={(state) => this.handleStateChange(state)}
                >
                    <Link onClick={() => this.closeMenu()} to="/" className="menu-item">Home</Link>
                    <Link onClick={() => this.closeMenu()} to="/about">About</Link>
                    <Link onClick={() => this.closeMenu()} to="/settings">Settings</Link>
                    {config.devModeEnabled() ?
                        <Link onClick={() => this.closeMenu()} to="/dev-settings">DEV Settings</Link>
                        : ''}
                </Menu>
                <div id="page-wrap">
                    <Route path="/" exact component={Upload}/>
                    <Route path="/about" exact component={About}/>
                    <Route path="/settings" exact component={Settings}/>
                    {config.devModeEnabled() ?
                        <Route path="/dev-settings" exact component={DevSettings}/>
                        : ''}
                </div>
            </Router>
        );
    }
}

export default App;
