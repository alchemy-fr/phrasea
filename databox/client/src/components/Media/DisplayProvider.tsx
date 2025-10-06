import {PropsWithChildren, useEffect, useState} from 'react';
import {
    DisplayContext,
    DisplayPreferences,
    PlayingContext,
} from './DisplayContext';
import {useUserPreferencesStore} from '../../store/userPreferencesStore.ts';

type Props = PropsWithChildren<{
    defaultState?: Partial<DisplayPreferences>;
}>;

export default function DisplayProvider({children, defaultState = {}}: Props) {
    const [playingContext, setPlayingContext] = useState<PlayingContext>();
    const displayPref = useUserPreferencesStore(s => s.preferences)?.display;
    const updatePreference = useUserPreferencesStore(s => s.updatePreference);

    const [state, setState] = useState<DisplayPreferences>({
        ...defaultState,
        thumbSize: 200,
        displayTitle: true,
        displayTags: true,
        displayPreview: true,
        titleRows: 1,
        displayCollections: true,
        displayAttributes: true,
        playVideos: false,
        collectionsLimit: 2,
        tagsLimit: 1,
        previewLocked: false,
        previewOptions: {
            sizeRatio: 60,
            attributesRatio: 30,
            displayFile: true,
            displayAttributes: true,
        },
        ...displayPref,
    });

    useEffect(() => {
        updatePreference('display', state);
    }, [state, updatePreference]);

    return (
        <DisplayContext.Provider
            value={{
                state,
                setState,
                playing: playingContext,
                setPlaying: (context: PlayingContext) => {
                    setPlayingContext(p => {
                        if (p && p !== context) {
                            p.stop();
                        }

                        return context;
                    });
                },
            }}
        >
            {children}
        </DisplayContext.Provider>
    );
}
