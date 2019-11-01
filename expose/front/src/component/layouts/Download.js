import React from 'react';
import {dataShape} from "../props/dataShape";

class DownloadLayout extends React.Component {
    static propTypes = {
        data: dataShape,
    };

    render() {
        const {
            title,
            assets,
        } = this.props.data;

        return <div className={`layout-download`}>
            <div className="container">
                <h1>{title}</h1>
                <h2>Download</h2>
                <ul className={'file-list'}>
                    {assets.map(a => {
                        const {downloadUrl, thumbUrl, originalName, mimeType, id} = a.asset;
                        return <li
                            key={id}
                        >
                            <a href={downloadUrl}>
                                <img src={thumbUrl} alt={originalName} />
                                {originalName} - {mimeType}
                            </a>
                        </li>
                    })}
                </ul>
            </div>
        </div>
    }
}

export default DownloadLayout;
