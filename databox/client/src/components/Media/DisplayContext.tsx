import React from 'react';

export type PlayingContext = {
    stop: VoidFunction;
};

export type TDisplayContext = {
    collectionsLimit: number;
    displayAttributes: boolean;
    displayCollections: boolean;
    displayPreview: boolean;
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
};

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
