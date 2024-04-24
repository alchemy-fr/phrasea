import React from 'react';
import videojs, {VideoJsPlayer} from 'video.js';
import {getPosterPlaceholder} from './placeholders';
import {WebVTTs} from "../../../types.ts";

type Props = {
    title?: string;
    description?: string;
    url: string;
    posterUrl?: string;
    webVTTLinks?: WebVTTs | undefined;
    fluid?: boolean;
    mimeType: string;
    assetId: string | undefined;
};

export default React.forwardRef(function VideoPlayer(
    {title, url, posterUrl, webVTTLinks, fluid, mimeType, assetId}: Props,
    ref
) {
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
                sources: [
                    {
                        src: url,
                        type: 'video/mp4',
                    },
                ],
                preload: 'metadata',
            });

            return () => {
                player.current?.dispose();
            };
        }
    }, []);

    React.useImperativeHandle(
        ref,
        () => {
            return {
                stop: () => {
                    const p = player.current;
                    if (p && !p.paused()) {
                        p?.pause();
                    }
                },
            };
        },
        []
    );

    return (
        <div className="video-container">
            <div data-vjs-player>
                <video
                    ref={setRef}
                    className="video-js vjs-big-play-centered"
                    data-matomo-resource={assetId}
                    data-matomo-title={title}
                >
                    {webVTTLinks && webVTTLinks.map(webVTTLink => <track
                            key={webVTTLink.label}
                            kind="captions"
                            src={webVTTLink.url}
                            srcLang={webVTTLink.locale}
                            label={webVTTLink.label}
                        />
                    )}
                </video>
            </div>
        </div>
    );
});
