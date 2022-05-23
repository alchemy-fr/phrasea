import React from "react";

export type TDisplayContext = {
    displayTitle: boolean;
    toggleDisplayTitle: () => void;
    titleRows: number;
    setTitleRows: (rows: number) => void;
    thumbSize: number;
    setThumbSize: (size: number) => void;
}

export const DisplayContext = React.createContext<TDisplayContext | null>(null);
