import React, {PureComponent} from 'react';
import {dataShape} from "../../props/dataShape";
import config from "../../../lib/config";
import Description from "./Description";
import ZippyDownloadButton from "./ZippyDownloadButton";
import moment from "moment";

export default class PublicationHeader extends PureComponent {
    static propTypes = {
        data: dataShape,
    };

    render() {
        const data = this.props.data;
        const {title, assets, description, layoutOptions, date} = data;

        return <div className={'pub-header'}>
            <div style={{
                position: 'relative',
            }}>
                {layoutOptions.logoUrl && <div className={'logo'}>
                    <img src={layoutOptions.logoUrl} alt={''}/>
                </div>}
                <h1>{title}</h1>
                {date ? <time>{moment(date).format('LLLL')}</time> : ''}
                {assets.length > 0 && config.zippyEnabled && <div style={{
                    position: 'absolute',
                    top: 0,
                    right: 0,
                }}>
                </div>}
            </div>
            {description && <Description
                descriptionHtml={description}
            />}
            {data.downloadEnabled && config.zippyEnabled && assets.length > 0 && <div className={'download-archive'}>
                <ZippyDownloadButton id={data.id} data={data} />
            </div>}
        </div>
    }

}

