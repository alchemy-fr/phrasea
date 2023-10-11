import React from 'react';
import videojs, {VideoJsPlayer} from 'video.js'
import {getPosterPlaceholder} from "./placeholders";

type Props = {
    title?: string,
    description?: string,
    url: string,
    posterUrl?: string,
    webVTTLink?: string,
    fluid?: boolean,
    mimeType: string,
    assetId: string | undefined,
};

export default React.forwardRef(function VideoPlayer({
    title,
    description,
    url,
    posterUrl,
    webVTTLink,
    fluid,
    mimeType,
    assetId,
}: Props, ref) {
    const player = React.useRef<VideoJsPlayer>();

    const setRef = React.useCallback((node: HTMLVideoElement) => {
        if (player.current) {
            // Make sure to cleanup any events/references added to the last instance
        }

        if (node) {
            player.current = videojs(node, {
                controls: true,
                fluid,
                poster: posterUrl || getPosterPlaceholder(mimeType),
                sources: [{
                    src: url,
                    type: 'video/mp4'
                }],
                preload: 'metadata',
            });

            return () => {
                player.current?.dispose();
            };
        }
    }, []);

    React.useImperativeHandle(ref, () => {
        return {
            stop: () => {
                const p = player.current;
                if (p && !p.paused()) {
                    p?.pause();
                }
            }
        };
    }, []);

    return <div
        className='video-container'
    >
        <div data-vjs-player>
            <video
                ref={setRef}
                className="video-js vjs-big-play-centered"
                data-matomo-resource={assetId}
                data-matomo-title={title}
            >
                {webVTTLink && <track
                    kind="captions"
                    src={webVTTLink}
                    srcLang="en"
                    label="English"
                    default
                />}
            </video>
        </div>
    </div>
});
