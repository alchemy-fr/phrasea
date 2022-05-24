import React from "react";

export type TDisplayContext = {
    displayTitle: boolean;
    displayCollections: boolean;
    toggleDisplayTitle: () => void;
    toggleDisplayCollections: () => void;
    collectionsLimit: number;
    setCollectionsLimit: (limit: number) => void;
    titleRows: number;
    setTitleRows: (rows: number) => void;
    thumbSize: number;
    setThumbSize: (size: number) => void;
    playVideos: boolean;
    togglePlayVideos: () => void;
}

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
