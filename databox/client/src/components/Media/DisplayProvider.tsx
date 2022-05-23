import React, {PropsWithChildren, useState} from "react";
import {DisplayContext} from "./DisplayContext";

export default function DisplayProvider({children}: PropsWithChildren<{}>) {
    const [thumbSize, setThumbSize] = useState(200);
    const [displayTitle, setDisplayTitle] = useState(true);
    const [titleRows, setTitleRows] = useState(1);

    return <DisplayContext.Provider value={{
        thumbSize,
        setThumbSize,
        displayTitle,
        toggleDisplayTitle: () => setDisplayTitle(p => !p),
        titleRows,
        setTitleRows,
    }}>
        {children}
    </DisplayContext.Provider>
}
