import React from 'react';
import {StateSetter} from '../../types.ts';
import {Layout} from '../AssetList/Layouts';

export type PlayingContext = {
    stop: VoidFunction;
};

export type PreviewOptions = {
    sizeRatio: number;
    attributesRatio: number;
    displayAttributes: boolean;
    displayFile: boolean;
};

export type DisplayPreferences = {
    displayPreview: boolean;
    previewOptions: PreviewOptions;
    playVideos: boolean;
    previewLocked: boolean;
    thumbSize: number;
    layout: Layout;
};

export type TDisplayContext = {
    setPlaying: (context: PlayingContext) => void;
    playing: PlayingContext | undefined;
    state: DisplayPreferences;
    setState: StateSetter<DisplayPreferences>;
    inOverflowDiv: boolean;
};

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
