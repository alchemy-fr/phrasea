import React from 'react';
import {useMatomo} from '@jonkoops/matomo-tracker-react';
import PDFViewer from './PDFViewer';
import VideoPlayer from './VideoPlayer';
import {
    Magnifier,
    MOUSE_ACTIVATION,
    TOUCH_ACTIVATION,
} from 'react-image-magnifiers/dist';
import {Asset} from '../../../types';
import {getTranslatedTitle} from '../../../i18n.ts';
import { useTranslation } from 'react-i18next';

type Props = {
    asset: Asset;
    magnifier?: boolean;
    isCurrent: boolean;
    fluid: boolean;
};

export default function AssetProxy({
    asset,
    magnifier,
    isCurrent,
    fluid,
}: Props) {
    const {t} = useTranslation();
    const containerRef = React.useRef<HTMLDivElement | null>(null);
    const videoRef = React.useRef<any>();
    const {pushInstruction} = useMatomo();

    React.useEffect(() => {
        if (!isCurrent && videoRef.current) {
            videoRef.current.stop();
        }
    }, [isCurrent]);

    const type = asset.mimeType;
    const mediaType = getMediaType(type);

    React.useEffect(() => {
        if (isCurrent && containerRef.current) {
            // TODO
            // if (asset.assetId) {
            //     pushInstruction('trackContentImpression', asset.title ?? asset.id, asset.assetId);
            // }

            if ([MediaType.Audio, MediaType.Video].includes(mediaType)) {
                if (process.env.NODE_ENV !== 'production') {
                    pushInstruction('MediaAnalytics::enableDebugMode');
                }

                pushInstruction('MediaAnalytics::setPingInterval', 10);
                pushInstruction(
                    'MediaAnalytics::scanForMedia',
                    containerRef.current
                );
            }
        }
    }, [containerRef, isCurrent, mediaType]);

    let content: JSX.Element;

    switch (mediaType) {
        case MediaType.Document:
            content = <PDFViewer file={asset.previewUrl} />;
            break;
        case MediaType.Video:
        case MediaType.Audio:
            content = (
                <VideoPlayer
                    ref={videoRef}
                    url={asset.previewUrl}
                    posterUrl={asset.posterUrl}
                    title={getTranslatedTitle(asset)}
                    webVTTLinks={isCurrent ? asset.webVTTLinks : undefined}
                    fluid={fluid}
                    mimeType={type}
                    assetId={asset.assetId}
                />
            );
            break;
        case MediaType.Image:
            if (magnifier) {
                content = (
                    <Magnifier
                        imageSrc={asset.previewUrl}
                        imageAlt={asset.title}
                        mouseActivation={MOUSE_ACTIVATION.CLICK} // Optional
                        touchActivation={TOUCH_ACTIVATION.DOUBLE_TAP} // Optional
                    />
                );
            } else {
                content = <img src={asset.previewUrl} alt={asset.title} />;
            }
            break;
        case MediaType.Unknown:
        default:
            content = <div>{t('asset_proxy.unsupported_media_type', `Unsupported media type`)}</div>;
            break;
    }

    return (
        <div ref={containerRef} className="asset-px">
            {content}
        </div>
    );
}

enum MediaType {
    Image,
    Video,
    Audio,
    Document,
    Unknown,
}

function getMediaType(type: string): MediaType {
    switch (true) {
        case 'application/pdf' === type:
            return MediaType.Document;
        case type.startsWith('video/'):
        case type.startsWith('audio/'):
            return MediaType.Video;
        case type.startsWith('image/'):
            return MediaType.Image;
        default:
            return MediaType.Unknown;
    }
}
