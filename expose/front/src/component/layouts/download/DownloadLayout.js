import React from 'react';
import {dataShape} from "../../props/dataShape";
import DownloadAsset from "./DownloadAsset";
import Description from "../shared-components/Description";
import {isTermsAccepted, setAcceptedTerms} from "../../../lib/credential";
import TermsModal from "../shared-components/TermsModal";
import DownloadViaEmailModal from "../shared-components/DownloadViaEmailModal";

const termsKeyPrefix = 'pd_';

class DownloadLayout extends React.Component {
    static propTypes = {
        data: dataShape,
    };

    state = {
        displayTerms: false,
        pendingDownloadUrl: null,
        displayDownloadViaEmail: false,
    }

    render() {
        const {
            title,
            assets,
            description,
        } = this.props.data;

        return <div className={`layout-download`}>
            {this.state.displayTerms ? this.renderTerms() : ''}
            {this.state.displayDownloadViaEmail ? this.renderDownloadViaEmail() : ''}
            <div className="container">
                <h1>{title}</h1>
                <Description
                    descriptionHtml={description}
                />
                <h2>Download</h2>
                <ul className={'file-list'}>
                    {assets.map(a => {
                        return <li
                            key={a.asset.id}
                        >
                            <DownloadAsset
                                onDownload={this.onDownload}
                                data={a.asset}
                            />
                        </li>
                    })}
                </ul>
            </div>
        </div>
    }

    onDownload = (url, e) => {
        const {data} = this.props;
        if (!data.downloadTerms.enabled || isTermsAccepted(termsKeyPrefix + data.id)) {
            if (true === data.downloadViaEmail) {
                e.preventDefault();
                this.setState({
                    displayDownloadViaEmail: true,
                    pendingDownloadUrl: url,
                });

                return;
            }

            return;
        }

        e.preventDefault();

        this.setState({
            displayTerms: true,
            pendingDownloadUrl: url,
        });
    }

    renderTerms() {
        const {text, url} = this.props.data.downloadTerms;

        return <TermsModal
            title={'Download'}
            closable={true}
            onClose={this.discardTerms}
            onAccept={this.acceptTerms}
            text={text}
            url={url}
        />
    }

    renderDownloadViaEmail() {
        return <DownloadViaEmailModal
            url={this.state.pendingDownloadUrl}
            onClose={this.discardDownloadViaEmail}
        />
    }

    discardTerms = () => {
        this.setState({
            displayTerms: false,
            pendingDownloadUrl: null,
        });
    }


    discardDownloadViaEmail = () => {
        this.setState({
            displayDownloadViaEmail: false,
            pendingDownloadUrl: null,
        });
    }

    acceptTerms = () => {
        const url = this.state.pendingDownloadUrl;
        setAcceptedTerms(termsKeyPrefix + this.props.data.id);
        this.setState({
            displayTerms: false,
            pendingDownloadUrl: null,
        }, () => {
            document.location.href = url;
        });
    }
}

export default DownloadLayout;
