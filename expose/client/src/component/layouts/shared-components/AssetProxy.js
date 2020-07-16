import React, {PureComponent} from 'react';
import {Magnifier, MOUSE_ACTIVATION, TOUCH_ACTIVATION} from "react-image-magnifiers";
import VideoPlayer from "./VideoPlayer";
import {Document, Page} from 'react-pdf'
import {PropTypes} from 'prop-types';

export default class AssetProxy extends PureComponent {
    static propTypes = {
        asset: PropTypes.object.isRequired,
    }

    render() {
        const {asset} = this.props;
        console.log('asset', asset);
        const type = asset.mimeType;

        switch (true) {
            case 'application/pdf' === type:
                return <Document file={asset.url}>
                    <Page />
                </Document>
            case type.startsWith('video/'):
                return <VideoPlayer
                    url={asset.url}
                    thumbUrl={asset.thumbUrl}
                    title={asset.title}
                    webVTTLink={asset.webVTTLink}
                />
            case type.startsWith('image/'):
                return <Magnifier
                    imageSrc={asset.url}
                    imageAlt={asset.title}
                    mouseActivation={MOUSE_ACTIVATION.CLICK} // Optional
                    touchActivation={TOUCH_ACTIVATION.DOUBLE_TAP} // Optional
                />
            default:
                return <div>Unsupported media type</div>
        }
    }
}
