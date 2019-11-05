import React from 'react';
import {assetShape} from "../../props/dataShape";

class DownloadAsset extends React.Component {
    static propTypes = {
        data: assetShape,
    };

    render() {
        const {
            downloadUrl,
            thumbUrl,
            originalName,
            mimeType,
        } = this.props.data;

        return <div>
            <a href={downloadUrl}>
                <img src={thumbUrl} alt={originalName} />
                {originalName} - {mimeType}
            </a>
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
                    <a href={d.downloadUrl}>
                        {d.name}
                    </a>
                </li>)}
            </ul>
        </div>
    }
}

export default DownloadAsset;
