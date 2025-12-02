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
    collectionsLimit: number;
    displayAttributes: boolean;
    displayCollections: boolean;
    displayPreview: boolean;
    previewOptions: PreviewOptions;
    displayTags: boolean;
    displayTitle: boolean;
    playVideos: boolean;
    previewLocked: boolean;
    tagsLimit: number;
    thumbSize: number;
    titleRows: number;
    layout: Layout;
};

export type TDisplayContext = {
    setPlaying: (context: PlayingContext) => void;
    playing: PlayingContext | undefined;
    state: DisplayPreferences;
    setState: StateSetter<DisplayPreferences>;
};

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
