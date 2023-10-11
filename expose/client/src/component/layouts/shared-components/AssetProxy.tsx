import React from 'react';
import {useMatomo} from "@jonkoops/matomo-tracker-react";
import PDFViewer from "./PDFViewer";
import VideoPlayer from "./VideoPlayer";
import {Magnifier, MOUSE_ACTIVATION, TOUCH_ACTIVATION} from "react-image-magnifiers/dist";
import {Asset} from "../../../types";

type Props = {
    asset: Asset,
    magnifier?: boolean,
    isCurrent: boolean,
    fluid: boolean,
};

export default function AssetProxy({
    asset,
    magnifier,
    isCurrent,
    fluid,
}: Props) {
    const containerRef = React.useRef<HTMLDivElement | null>(null);
    const videoRef = React.useRef<any>();
    const {pushInstruction} = useMatomo();

    React.useEffect(() => {
        if (!isCurrent && videoRef.current) {
            videoRef.current.stop();
        }
    }, [isCurrent]);

    React.useEffect(() => {
        if (isCurrent && containerRef.current) {
            pushInstruction('MediaAnalytics::enableMediaAnalytics');
            pushInstruction('MediaAnalytics::setPingInterval', 1);
            pushInstruction('MediaAnalytics::scanForMedia');
        }
    }, [containerRef, isCurrent]);

    let content: JSX.Element;
    const type = asset.mimeType;

    switch (true) {
        case 'application/pdf' === type:
            content = <PDFViewer file={asset.previewUrl}/>
            break;
        case type.startsWith('video/'):
        case type.startsWith('audio/'):
            content = <VideoPlayer
                ref={videoRef}
                url={asset.previewUrl}
                posterUrl={asset.posterUrl}
                title={asset.title}
                webVTTLink={asset.webVTTLink}
                fluid={fluid}
                mimeType={type}
                assetId={asset.assetId}
            />
            break;
        case type.startsWith('image/'):
            if (magnifier) {
                content = <Magnifier
                    imageSrc={asset.previewUrl}
                    imageAlt={asset.title}
                    mouseActivation={MOUSE_ACTIVATION.CLICK} // Optional
                    touchActivation={TOUCH_ACTIVATION.DOUBLE_TAP} // Optional
                />
            } else {
                content = <img
                    src={asset.previewUrl}
                    alt={asset.title}
                />
            }
            break;
        default:
            content = <div>Unsupported media type</div>
            break;
    }

    return <div
        ref={containerRef}
        className="asset-px"
    >
        {content}
    </div>
}
