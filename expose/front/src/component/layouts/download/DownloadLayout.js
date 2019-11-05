import React from 'react';
import {dataShape} from "../../props/dataShape";
import DownloadAsset from "./DownloadAsset";

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
                        return <li
                            key={a.asset.id}
                        >
                            <DownloadAsset
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
