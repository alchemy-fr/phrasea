import React, {Component} from 'react';

const config = window.config.client.logo;

export default class Logo extends Component {
    render() {
        return (
            <div className="logo" style={{
                margin: config.margin
            }}>
                <img src={config.src} alt="Uploader" />
            </div>
        );
    }
}
