import React from "react";

export type PlayingContext = {
    stop: () => void;
}

export type TDisplayContext = {
    displayTitle: boolean;
    displayTags: boolean;
    displayCollections: boolean;
    toggleDisplayTitle: () => void;
    toggleDisplayTags: () => void;
    tagsLimit: number;
    setTagsLimit: (limit: number) => void;
    toggleDisplayCollections: () => void;
    collectionsLimit: number;
    setCollectionsLimit: (limit: number) => void;
    titleRows: number;
    setTitleRows: (rows: number) => void;
    thumbSize: number;
    setThumbSize: (size: number) => void;
    playVideos: boolean;
    togglePlayVideos: () => void;
    displayPreview: boolean;
    toggleDisplayPreview: () => void;
    playing: PlayingContext | undefined;
    setPlaying: (context: PlayingContext) => void;
    previewLocked: boolean;
}

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
