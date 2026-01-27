import {StrictDimensions} from '@alchemy/core';

export type AssetFile = {
    id: string;
    name: string;
    type: string;
    url: string;
};

export type WebVTTLink = {
    id: string;
    kind?: 'subtitles' | 'captions' | 'descriptions' | 'chapters' | 'metadata';
    label: string;
    locale: string;
    url: string;
};

export type FilePlayerProps = {
    file: AssetFile;
    onLoad?: (() => void) | undefined;
    noInteraction?: boolean | undefined;
    title: string | undefined;
    controls?: boolean | undefined;
    autoPlayable?: boolean | undefined;
    dimensions: StrictDimensions;
    webVTTLinks?: WebVTTLink[];
};

export type ZoomStepState = {
    current: number;
    maxReached: number;
};

export enum FilePlayerClasses {
    PlayerControls = 'fp-player-controls',
    VideoPlayer = 'fp-video-player',
    IsAudio = 'fp-is-audio',
    Controls = 'fp-controls',
    Playing = 'fp-playing',
    VideoPlayControl = 'fp-video-play-control',
}
