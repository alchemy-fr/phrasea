import React, {PureComponent} from 'react';
import {Magnifier, MOUSE_ACTIVATION, TOUCH_ACTIVATION} from "react-image-magnifiers";
import VideoPlayer from "./VideoPlayer";
import {PropTypes} from 'prop-types';
import PDFViewer from "./PDFViewer";

export default class AssetProxy extends PureComponent {
    static propTypes = {
        asset: PropTypes.object.isRequired,
        magnifier: PropTypes.bool,
        isCurrent: PropTypes.bool,
    }

    constructor(props) {
        super(props);

        this.videoRef = React.createRef();
    }

    componentDidUpdate(prevProps, prevState, snapshot) {
        if (prevProps.isCurrent !== this.props.isCurrent && !this.props.isCurrent) {
            this.stop();
        }
    }

    render() {
        return <div className="asset-px">
            {this.renderContent()}
        </div>
    }

    stop() {
        if (this.videoRef.current) {
            this.videoRef.current.stop();
        }
    }

    renderContent() {
        const {asset} = this.props;
        const type = asset.mimeType;

        switch (true) {
            case 'application/pdf' === type:
                return <PDFViewer file={asset.url}/>
            case type.startsWith('video/'):
                return <VideoPlayer
                    ref={this.videoRef}
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
