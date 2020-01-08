import React, {Component} from 'react';
import config from '../config';

const logo = config.all().client.logo;

export default class Logo extends Component {
    render() {
        return (
            <div className="logo" style={{
                margin: logo.margin
            }}>
                <img src={logo.src} alt="Uploader" />
            </div>
        );
    }
}
