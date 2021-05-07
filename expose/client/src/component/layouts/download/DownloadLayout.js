import React from 'react';
import {dataShape} from "../../props/dataShape";
import DownloadAsset from "./DownloadAsset";
import Description from "../shared-components/Description";
import {
    downloadContainerDefaultState, onDownload,
    renderDownloadTermsModal, renderDownloadViaEmail
} from "../shared-components/DownloadViaEmailProxy";
import config from "../../../lib/config";
import ZippyDownloadButton from "../shared-components/ZippyDownloadButton";

class DownloadLayout extends React.Component {
    static propTypes = {
        data: dataShape,
    };

    state = downloadContainerDefaultState;

    render() {
        const {data} = this.props;
        const {
            title,
            assets,
            description,
        } = data;

        return <div className={`layout-download`}>
            {renderDownloadTermsModal.call(this)}
            {renderDownloadViaEmail.call(this)}
            <div className="container">
                <div style={{
                    position: 'relative',
                }}>
                    <h1>{title}</h1>
                    {config.get('zippyEnabled') && <div style={{
                        position: 'absolute',
                        top: 0,
                        right: 0,
                    }}>
                        <ZippyDownloadButton id={data.id} />
                    </div>}
                </div>
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
                                onDownload={onDownload.bind(this)}
                                data={a.asset}
                            />
                        </li>
                    })}
                </ul>
            </div>
        </div>
    }
}

export default DownloadLayout;
