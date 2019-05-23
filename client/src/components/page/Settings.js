import React, {Component} from 'react';
import ChangePassword from "./ChangePassword";

export default class Settings extends Component {
    render() {
        return (
            <div className="container">
                <h1>Settings</h1>
                <ChangePassword />
            </div>
        );
    }
}
