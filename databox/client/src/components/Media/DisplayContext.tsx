import React from 'react';
import {StateSetter} from '../../types.ts';

export type PlayingContext = {
    stop: VoidFunction;
};

export type PreviewOptions = {
    sizeRatio: number;
    attributesRatio: number;
    displayAttributes: boolean;
    displayFile: boolean;
};

export type TDisplayContext = {
    collectionsLimit: number;
    displayAttributes: boolean;
    displayCollections: boolean;
    displayPreview: boolean;
    previewOptions: PreviewOptions;
    displayTags: boolean;
    displayTitle: boolean;
    playVideos: boolean;
    playing: PlayingContext | undefined;
    previewLocked: boolean;
    setCollectionsLimit: (limit: number) => void;
    setPlaying: (context: PlayingContext) => void;
    setTagsLimit: (limit: number) => void;
    setThumbSize: (size: number) => void;
    setTitleRows: (rows: number) => void;
    tagsLimit: number;
    thumbSize: number;
    titleRows: number;
    toggleDisplayAttributes: VoidFunction;
    toggleDisplayCollections: VoidFunction;
    toggleDisplayPreview: VoidFunction;
    toggleDisplayTags: VoidFunction;
    toggleDisplayTitle: VoidFunction;
    togglePlayVideos: VoidFunction;
    setPreviewOptions: StateSetter<PreviewOptions>;
};

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
