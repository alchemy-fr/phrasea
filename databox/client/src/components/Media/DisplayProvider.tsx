import {PropsWithChildren, useEffect, useState} from 'react';
import {DisplayContext, PlayingContext} from './DisplayContext';
import {toast} from 'react-toastify';

import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{
    thumbSize?: number;
    displayTitle?: boolean;
    displayTags?: boolean;
    displayPreview?: boolean;
    titleRows?: number;
    displayCollections?: boolean;
    displayAttributes?: boolean;
    playVideos?: boolean;
    collectionsLimit?: number;
    tagsLimit?: number;
    playingContext?: PlayingContext;
    previewLocked?: boolean;
}>;

export default function DisplayProvider({
    children,
    thumbSize: defaultThumbSize = 200,
    displayTitle: defaultDisplayTitle = true,
    displayTags: defaultDisplayTags = true,
    displayPreview: defaultDisplayPreview = true,
    titleRows: defaultTitleRows = 1,
    displayCollections: defaultDisplayCollections = true,
    displayAttributes: defaultDisplayAttributes = true,
    playVideos: defaultPlayVideos = false,
    collectionsLimit: defaultCollectionsLimit = 2,
    tagsLimit: defaultTagsLimit = 1,
    playingContext: defaultPlayingContext,
    previewLocked: defaultPreviewLocked = false,
}: Props) {
    const [thumbSize, setThumbSize] = useState<number>(defaultThumbSize);
    const [displayTitle, setDisplayTitle] =
        useState<boolean>(defaultDisplayTitle);
    const [displayTags, setDisplayTags] = useState<boolean>(defaultDisplayTags);
    const [displayPreview, setDisplayPreview] = useState<boolean>(
        defaultDisplayPreview
    );
    const [titleRows, setTitleRows] = useState<number>(defaultTitleRows);
    const [displayCollections, setDisplayCollections] = useState<boolean>(
        defaultDisplayCollections
    );
    const [displayAttributes, setDisplayAttributes] = useState<boolean>(
        defaultDisplayAttributes
    );
    const [playVideos, setPlayVideos] = useState<boolean>(defaultPlayVideos);
    const [collectionsLimit, setCollectionsLimit] = useState<number>(
        defaultCollectionsLimit
    );
    const [tagsLimit, setTagsLimit] = useState<number>(defaultTagsLimit);
    const [playingContext, setPlayingContext] = useState<
        PlayingContext | undefined
    >(defaultPlayingContext);
    const [previewLocked, setPreviewLocked] =
        useState<boolean>(defaultPreviewLocked);

    const {t} = useTranslation();

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (
                document.activeElement &&
                document.activeElement?.getAttribute('type') === 'text'
            ) {
                return;
            }
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                toast.info(
                    previewLocked
                        ? (t(
                              'layout.previews_unlocked',
                              'Previews unlocked'
                          ) as string)
                        : (t(
                              'layout.previews_locked',
                              'Previews locked'
                          ) as string),
                    {
                        toastId: 'preview_lock',
                        updateId: 'preview_lock',
                    }
                );

                setPreviewLocked(!previewLocked);
            }
        };

        window.addEventListener('keydown', handler);

        return () => {
            window.removeEventListener('keydown', handler);
        };
    }, [previewLocked]);

    return (
        <DisplayContext.Provider
            value={{
                collectionsLimit,
                displayAttributes,
                displayCollections,
                displayPreview,
                displayTags,
                displayTitle,
                playVideos,
                playing: playingContext,
                previewLocked,
                setCollectionsLimit,
                setPlaying: context => {
                    setPlayingContext(p => {
                        if (p && p !== context) {
                            p.stop();
                        }

                        return context;
                    });
                },
                setTagsLimit,
                setThumbSize,
                setTitleRows,
                tagsLimit,
                thumbSize,
                titleRows,
                toggleDisplayAttributes: () => setDisplayAttributes(p => !p),
                toggleDisplayCollections: () => setDisplayCollections(p => !p),
                toggleDisplayPreview: () => setDisplayPreview(p => !p),
                toggleDisplayTags: () => setDisplayTags(p => !p),
                toggleDisplayTitle: () => setDisplayTitle(p => !p),
                togglePlayVideos: () => setPlayVideos(p => !p),
            }}
        >
            {children}
        </DisplayContext.Provider>
    );
}
