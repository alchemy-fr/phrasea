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
            downloadUrl,
            thumbUrl,
            originalName,
            mimeType,
            description,
        } = this.props.data;

        return <div>
            <a
                onClick={e => this.props.onDownload(downloadUrl, e)}
                href={downloadUrl || '#'}
            >
                <img src={thumbUrl} alt={originalName}/>
                {originalName} - {mimeType}
            </a>
            <Description
                descriptionHtml={description}
            />
            {this.renderSubDef()}
        </div>
    }

    renderSubDef() {
        const {
            subDefinitions,
        } = this.props.data;

        if (subDefinitions.length === 0) {
            return '';
        }

        return <div>
            <div>Sub definitions</div>
            <ul>
                {subDefinitions.map(d => <li
                    key={d.id}
                >
                    <a
                        onClick={e => this.props.onDownload(d.downloadUrl, e)}
                        href={d.downloadUrl || '#'}
                    >
                        {d.name}
                    </a>
                </li>)}
            </ul>
        </div>
    }
}

export default DownloadAsset;
