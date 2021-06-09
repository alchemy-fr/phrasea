import React from 'react';
import {assetShape} from "../../props/dataShape";
import Description from "../shared-components/Description";
import {PropTypes} from 'prop-types';

class DownloadAsset extends React.Component {
    static propTypes = {
        data: assetShape,
        onDownload: PropTypes.func,
    };

    render() {
        const {
            thumbUrl,
            originalName,
            mimeType,
            description,
        } = this.props.data;

        return <div className="media">
            <img src={thumbUrl} alt={originalName}/>
            <div className="media-body">
                <h5 className="mt-0">
                    {originalName} - {mimeType}
                </h5>
                <Description
                    descriptionHtml={description}
                />
                {this.renderSubDef()}
            </div>
        </div>
    }

    renderSubDef() {
        const {
            subDefinitions,
            id,
            downloadUrl,
        } = this.props.data;

        return <div className={'download-btns'}>
            <a
                onClick={e => this.props.onDownload(downloadUrl, e)}
                href={downloadUrl || '#'}
                className={'btn btn-primary'}
            >
                Download original
            </a>
            {subDefinitions.map(d => <a
                key={d.id}
                onClick={e => this.props.onDownload(d.downloadUrl, id, e)}
                href={d.downloadUrl || '#'}
                className={'btn btn-secondary'}
            >
                Download {d.name}
            </a>)}
        </div>
    }
}

export default DownloadAsset;
