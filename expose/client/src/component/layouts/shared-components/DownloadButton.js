import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Translation} from "react-i18next";

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

        return <Translation>
            {t => <a
                className={'btn btn-secondary'}
                href={downloadUrl}
                type={'button'}
                title={t('download')}
                onClick={this.onDownload}
            >
                {t('download')}
            </a>}
        </Translation>
    }
}

