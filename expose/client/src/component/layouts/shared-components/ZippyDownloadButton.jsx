import React, { PureComponent } from 'react'
import {
    renderDownloadTermsModal,
    renderDownloadViaEmail,
    termsKeyPrefix,
} from './DownloadViaEmailProxy'
import { isTermsAccepted } from '../../../lib/credential'
import { Trans } from 'react-i18next'

export default class ZippyDownloadButton extends PureComponent {
    state = {
        disabled: false,
    }

    onDownload = () => {
        const { data } = this.props
        if (
            !data.downloadTerms.enabled ||
            isTermsAccepted(termsKeyPrefix + data.id)
        ) {
            if (true === data.downloadViaEmail) {
                this.setState({
                    displayDownloadViaEmail: true,
                    pendingDownloadUrl: this.props.data.archiveDownloadUrl,
                })

                return
            }

            this.disableButtonForDownload()
            window.open(this.props.data.archiveDownloadUrl, '_blank')
        }

        this.setState({
            displayDownloadTerms: true,
            pendingDownloadUrl: this.props.data.archiveDownloadUrl,
        })
    }

    disableButtonForDownload() {
        this.setState(
            (prevState) => {
                if (prevState.disabled) {
                    return
                }

                return { disabled: true }
            },
            () => {
                setTimeout(() => {
                    this.setState({ disabled: false })
                }, 5000)
            }
        )
    }

    render() {
        return (
            <>
                {renderDownloadTermsModal.call(this)}
                {renderDownloadViaEmail.call(this)}
                <button
                    disabled={this.state.disabled}
                    className={'btn btn-secondary'}
                    type={'button'}
                    title={'Download'}
                    onClick={this.onDownload}
                >
                    <span role={'img'}>🗜</span>️
                    <Trans i18nKey={'download_archive'}>Download archive</Trans>
                </button>
            </>
        )
    }
}
