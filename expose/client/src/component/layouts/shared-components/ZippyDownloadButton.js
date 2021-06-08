import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import config from '../../../lib/config';
import apiClient from "../../../lib/apiClient";
import {dataShape} from "../../props/dataShape";
import {renderDownloadTermsModal, renderDownloadViaEmail, termsKeyPrefix} from "./DownloadViaEmailProxy";
import {isTermsAccepted} from "../../../lib/credential";

export default class ZippyDownloadButton extends PureComponent {
    static propTypes = {
        id: PropTypes.string.isRequired,
        data: dataShape.isRequired,
    };

    state = {
        disabled: false,
    };

    onDownload = () => {
        const {data} = this.props;
        if (!data.downloadTerms.enabled || isTermsAccepted(termsKeyPrefix + data.id)) {
            if (true === data.downloadViaEmail) {
                this.setState({
                    displayDownloadViaEmail: true,
                    pendingDownloadUrl: this.props.data.archiveDownloadUrl
                });

                return;
            }

            this.disableButtonForDownload();
            window.open(this.props.data.archiveDownloadUrl, '_blank');
        }

        this.setState({
            displayDownloadTerms: true,
        });
    }

    disableButtonForDownload() {
        this.setState(prevState => {
            if (prevState.disabled) {
                return;
            }

            return {disabled: true};
        }, () => {
            setTimeout(() => {
                this.setState({disabled: false});
            }, 5000);
        });
    }

    render() {
        return <>
            {renderDownloadTermsModal.call(this)}
            {renderDownloadViaEmail.call(this)}
            <button
                disabled={this.state.disabled}
                className={'btn btn-secondary'}
                type={'button'}
                title={'Download'}
                onClick={this.onDownload}
            >
                <span role={'img'}>🗜</span>️ Download archive
            </button>
        </>
    }
}

