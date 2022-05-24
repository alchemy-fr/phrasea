import React, {PropsWithChildren, useState} from "react";
import {DisplayContext} from "./DisplayContext";

export default function DisplayProvider({children}: PropsWithChildren<{}>) {
    const [thumbSize, setThumbSize] = useState(200);
    const [displayTitle, setDisplayTitle] = useState(true);
    const [titleRows, setTitleRows] = useState(1);
    const [displayCollections, setDisplayCollections] = useState(true);
    const [playVideos, setPlayVideos] = useState(false);
    const [collectionsLimit, setCollectionsLimit] = useState(2);

    return <DisplayContext.Provider value={{
        thumbSize,
        setThumbSize,
        displayTitle,
        toggleDisplayTitle: () => setDisplayTitle(p => !p),
        toggleDisplayCollections: () => setDisplayCollections(p => !p),
        titleRows,
        collectionsLimit,
        setCollectionsLimit,
        setTitleRows,
        displayCollections,
        playVideos,
        togglePlayVideos: () => setPlayVideos(p => !p),
    }}>
        {children}
    </DisplayContext.Provider>
}
