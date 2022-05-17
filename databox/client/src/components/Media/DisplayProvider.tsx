import React, {PropsWithChildren, useState} from "react";
import {AssetSelectionContext} from "./AssetSelectionContext";
import {DisplayContext, TDisplayContext} from "./DisplayContext";

export default function DisplayProvider({children}: PropsWithChildren<{}>) {
    const [thumbSize, setThumbSize] = useState(200);

    return <DisplayContext.Provider value={{
        thumbSize,
        setThumbSize,
    }}>
        {children}
    </DisplayContext.Provider>
}
