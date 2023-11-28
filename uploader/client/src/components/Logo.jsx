import React, {Component} from 'react';
import config from '../config';

export default class Logo extends Component {
    render() {
        const logo = config.client?.logo;

        if (!logo) {
            return <></>
        }

        return (
            <div className="logo" style={{
                margin: logo.margin
            }}>
                <img src={logo.src} alt="Uploader" />
            </div>
        );
    }
}
