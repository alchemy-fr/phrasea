import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from '../../../lib/config';
import apiClient from "../../../lib/apiClient";

export default class ZippyDownloadButton extends PureComponent {
    static propTypes = {
        id: PropTypes.string.isRequired,
    };

    state = {
        disabled: false,
    };

    onDownload = () => {
        let download = false;
        this.setState(prevState => {
            if (prevState.disabled) {
                return;
            }

            download = true;

            return {disabled: true};
        }, async () => {
            const res = await apiClient.get(`${config.getApiBaseUrl()}/publications/${this.props.id}/download-via-zippy`);

            setTimeout(() => {
                this.setState({disabled: false});
            }, 5000);

            window.open(res.downloadUrl, '_blank');
        });
    }

    render() {
        return <button
            disabled={this.state.disabled}
            className={'btn btn-secondary'}
            type={'button'}
            title={'Download'}
            onClick={this.onDownload}
        >
            🗜️ Download archive
        </button>
    }
}

