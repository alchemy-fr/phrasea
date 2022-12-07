import React from 'react';
import {dataShape} from "../../props/dataShape";
import DownloadAsset from "./DownloadAsset";
import {
    downloadContainerDefaultState, onDownload,
    renderDownloadTermsModal, renderDownloadViaEmail
} from "../shared-components/DownloadViaEmailProxy";
import PublicationHeader from "../shared-components/PublicationHeader";

class DownloadLayout extends React.Component {
    static propTypes = {
        data: dataShape,
    };

    state = downloadContainerDefaultState;

    render() {
        const {data} = this.props;
        const {
            assets,
            downloadEnabled,
        } = data;

        if (!downloadEnabled) {
            return <div>
                Download is disabled.
            </div>
        }

        return <div className={`layout-download`}>
            {renderDownloadTermsModal.call(this)}
            {renderDownloadViaEmail.call(this)}
            <PublicationHeader
                data={data}
            />
            <div className={'file-list'}>
                {assets.map(a => {
                    return <DownloadAsset
                        key={a.id}
                        onDownload={onDownload.bind(this)}
                        data={a}
                    />
                })}
            </div>
        </div>
    }
}

export default DownloadLayout;
