import React, {Component} from 'react';

export default class Logo extends Component {
    render() {
        return (
            <div className="logo" style={{
                margin: `${window._env_.CLIENT_LOGO_Y_MARGIN}px ${window._env_.CLIENT_LOGO_X_MARGIN}px `
            }}>
                <img src={window._env_.CLIENT_LOGO_SRC} alt="Uploader" />
            </div>
        );
    }
}
