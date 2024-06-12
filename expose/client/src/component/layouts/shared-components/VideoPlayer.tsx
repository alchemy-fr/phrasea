import React from 'react';
import videojs, {VideoJsPlayer} from 'video.js';
import {getPosterPlaceholder} from './placeholders';
import {WebVTTs} from '../../../types.ts';

type Props = {
    title?: string;
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
    const playerRef = React.useRef<VideoJsPlayer | null>(null);
    const videoRef = React.useRef<HTMLDivElement | null>(null);

    React.useEffect(() => {
        const tracks = webVTTLinks?.map(webVTTLink => ({
            kind: webVTTLink.kind ?? 'subtitles',
            src: webVTTLink.url,
            language: webVTTLink.locale,
            srclang: webVTTLink.locale,
            label: webVTTLink.label,
            id: webVTTLink.id,
        }));

        if (!playerRef.current) {
            const videoElement = document.createElement('video');

            videoElement.classList.add('video-js');
            videoElement.classList.add('vjs-big-play-centered');
            if (assetId) {
                videoElement.setAttribute('data-matomo-resource', assetId);
            }
            if (title) {
                videoElement.setAttribute('data-matomo-title', title);
            }
            videoRef.current!.appendChild(videoElement);

            playerRef.current = videojs(videoElement, {
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
                tracks,
            });
        } else {
            const player = playerRef.current!;
            const oldTracks = player.remoteTextTracks();
            if (tracks) {
                if (oldTracks.length === 0) {
                    tracks.forEach(tt => player.addRemoteTextTrack(tt, false));
                }
            }
        }
    }, [videoRef, webVTTLinks]);

    React.useEffect(() => {
        const player = playerRef.current;

        return () => {
            if (player && !player.isDisposed()) {
                player.dispose();
                playerRef.current = null;
            }
        };
    }, [playerRef]);

    React.useImperativeHandle(ref, () => {
        return {
            stop: () => {
                const p = playerRef.current;
                if (p && !p.paused()) {
                    p?.pause();
                }
            },
        };
    }, []);

    return (
        <div className="video-container">
            <div data-vjs-player>
                <div ref={videoRef} />
            </div>
        </div>
    );
});
