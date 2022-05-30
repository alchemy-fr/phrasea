import React, {PropsWithChildren, useEffect, useState} from "react";
import {DisplayContext, PlayingContext} from "./DisplayContext";
import {toast} from "react-toastify";

import {useTranslation} from 'react-i18next';

export default function DisplayProvider({children}: PropsWithChildren<{}>) {
    const [thumbSize, setThumbSize] = useState(200);
    const [displayTitle, setDisplayTitle] = useState(true);
    const [displayTags, setDisplayTags] = useState(true);
    const [displayPreview, setDisplayPreview] = useState(true);
    const [titleRows, setTitleRows] = useState(1);
    const [displayCollections, setDisplayCollections] = useState(true);
    const [playVideos, setPlayVideos] = useState(false);
    const [collectionsLimit, setCollectionsLimit] = useState(2);
    const [tagsLimit, setTagsLimit] = useState(1);
    const [playingContext, setPlayingContext] = useState<PlayingContext>();
    const [previewLocked, setPreviewLocked] = useState(false);

    const {t} = useTranslation();

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (document.activeElement && document.activeElement?.getAttribute('type') === 'text') {
                return;
            }
            if (e.code === 'KeyP') {
                toast.info(previewLocked
                    ? t('layout.previews_unlocked', 'Previews unlocked')
                    : t('layout.previews_locked', 'Previews locked')
                , {
                    toastId: 'preview_lock',
                        updateId: 'preview_lock',
                    });

                setPreviewLocked(!previewLocked);
            }
        };

        window.addEventListener('keypress', handler);

        return () => {
            window.removeEventListener('keypress', handler);
        }
    }, [previewLocked]);

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
        tagsLimit,
        setTagsLimit,
        displayTags,
        toggleDisplayTags: () => setDisplayTags(p => !p),
        displayPreview,
        toggleDisplayPreview: () => setDisplayPreview(p => !p),
        playing: playingContext,
        setPlaying: (context) => {
            setPlayingContext(p => {
                if (p && p !== context) {
                    p.stop();
                }

                return context;
            })
        },
        previewLocked,
    }}>
        {children}
    </DisplayContext.Provider>
}
