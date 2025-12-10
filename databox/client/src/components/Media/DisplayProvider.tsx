import {PropsWithChildren, useEffect, useState} from 'react';
import {
    DisplayContext,
    DisplayPreferences,
    PlayingContext,
    TDisplayContext,
} from './DisplayContext';
import {useUserPreferencesStore} from '../../store/userPreferencesStore.ts';
import {Layout} from '../AssetList/Layouts';

type Props = PropsWithChildren<{
    defaultState?: Partial<DisplayPreferences>;
    inOverflowDiv?: TDisplayContext['inOverflowDiv'];
    displayPrefKey?: 'display' | 'displayBatchEdit';
}>;

export default function DisplayProvider({
    children,
    displayPrefKey = 'display',
    inOverflowDiv = false,
    defaultState = {},
}: Props) {
    const [playingContext, setPlayingContext] = useState<PlayingContext>();
    const displayPref = useUserPreferencesStore(s => s.preferences)?.[
        displayPrefKey
    ];
    const updatePreference = useUserPreferencesStore(s => s.updatePreference);

    const [state, setState] = useState<DisplayPreferences>({
        layout: Layout.Grid,
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
        ...(defaultState ?? {}),
        ...(displayPref ?? {}),
        previewOptions: {
            sizeRatio: 60,
            attributesRatio: 30,
            displayFile: true,
            displayAttributes: true,
            ...(defaultState?.previewOptions ?? {}),
            ...(displayPref?.previewOptions ?? {}),
        },
    });

    useEffect(() => {
        updatePreference(displayPrefKey, state);
    }, [state, updatePreference, displayPrefKey]);

    return (
        <DisplayContext.Provider
            value={{
                inOverflowDiv,
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
