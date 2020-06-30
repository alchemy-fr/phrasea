import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';

export default class DownloadButton extends PureComponent {
    static propTypes = {
        downloadUrl: PropTypes.string,
        onDownload: PropTypes.func.isRequired,
    };

    onDownload = (e) => {
        this.props.onDownload(this.props.downloadUrl, e);
    }

    render() {
        const {downloadUrl} = this.props;

        if (!downloadUrl) {
            return '';
        }

        return <button
            className={'btn btn-secondary'}
            type={'button'}
            title={'Download'}
            onClick={this.onDownload}
        >
            Download
        </button>
    }
}

