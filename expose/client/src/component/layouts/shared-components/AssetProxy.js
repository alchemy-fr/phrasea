import React, {PureComponent} from 'react';
import {Magnifier, MOUSE_ACTIVATION, TOUCH_ACTIVATION} from "react-image-magnifiers";
import VideoPlayer from "./VideoPlayer";
import {PropTypes} from 'prop-types';
import PDFViewer from "./PDFViewer";

export default class AssetProxy extends PureComponent {
    static propTypes = {
        asset: PropTypes.object.isRequired,
        magnifier: PropTypes.bool,
    }

    render() {
        return <div className="asset-px">
            {this.renderContent()}
        </div>
    }

    renderContent() {
        const {asset} = this.props;
        const type = asset.mimeType;

        switch (true) {
            case 'application/pdf' === type:
                return <PDFViewer file={asset.url}/>
            case type.startsWith('video/'):
                return <VideoPlayer
                    url={asset.url}
                    previewUrl={asset.previewUrl}
                    title={asset.title}
                    webVTTLink={asset.webVTTLink}
                />
            case type.startsWith('image/'):
                if (this.props.magnifier) {
                    return <Magnifier
                        imageSrc={asset.previewUrl}
                        imageAlt={asset.title}
                        mouseActivation={MOUSE_ACTIVATION.CLICK} // Optional
                        touchActivation={TOUCH_ACTIVATION.DOUBLE_TAP} // Optional
                    />
                }

                return <img
                    src={asset.previewUrl}
                    alt={asset.title}
                />
            default:
                return <div>Unsupported media type</div>
        }
    }
}
