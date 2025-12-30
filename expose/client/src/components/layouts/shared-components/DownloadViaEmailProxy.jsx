import React from 'react';
import DownloadViaEmailModal from './DownloadViaEmailModal';
import {isTermsAccepted, setAcceptedTerms} from '../../../lib/credential';

export const downloadContainerDefaultState = {
    displayDownloadTerms: false,
    pendingDownloadUrl: null,
    displayDownloadViaEmail: false,
};

export const termsKeyPrefix = 'pd_';

function discardTerms() {
    this.setState({
        displayDownloadTerms: false,
        pendingDownloadUrl: null,
    });
}

function acceptTerms() {
    const url = this.state.pendingDownloadUrl;
    setAcceptedTerms(termsKeyPrefix + this.props.data.id);

    const newState = {
        displayDownloadTerms: false,
        pendingDownloadUrl: null,
    };

    const downloadViaEmail = true === this.props.data.downloadViaEmail;
    if (downloadViaEmail) {
        newState.displayDownloadViaEmail = true;
        newState.pendingDownloadUrl = url;
    }
    this.setState(newState, () => {
        if (!downloadViaEmail) {
            document.location.href = url;
        }
    });
}

export function renderDownloadTermsModal() {
    if (!this.state.displayDownloadTerms) {
        return '';
    }

    const {text, url} = this.props.data.downloadTerms;

    return (
        <TermsModal
            title={'Download'}
            closable={true}
            onClose={discardTerms.bind(this)}
            onAccept={acceptTerms.bind(this)}
            text={text}
            url={url}
        />
    );
}

export function onDownload(url, e) {
    const {data} = this.props;
    if (
        !data.downloadTerms.enabled ||
        isTermsAccepted(termsKeyPrefix + data.id)
    ) {
        if (true === data.downloadViaEmail) {
            e.preventDefault();
            this.setState({
                displayDownloadViaEmail: true,
                pendingDownloadUrl: url,
            });

            return false;
        }

        return true;
    }

    e.preventDefault();

    this.setState({
        displayDownloadTerms: true,
        pendingDownloadUrl: url,
    });

    return false;
}

function discardDownloadViaEmail() {
    this.setState({
        displayDownloadViaEmail: false,
        pendingDownloadUrl: null,
    });
}

export function renderDownloadViaEmail() {
    if (!this.state.displayDownloadViaEmail) {
        return '';
    }

    return (
        <DownloadViaEmailModal
            url={this.state.pendingDownloadUrl}
            onClose={discardDownloadViaEmail.bind(this)}
        />
    );
}
