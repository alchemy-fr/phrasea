import React from "react";

export type TDisplayContext = {
    thumbSize: number;
    setThumbSize: (size: number) => void;
}

export const DisplayContext = React.createContext<TDisplayContext>({
    thumbSize: 120,
    setThumbSize: (size: number) => {},
});
