import {PropsWithChildren, useEffect, useMemo, useState} from 'react';
import {
    DisplayContext,
    DisplayPreferences,
    PlayingContext,
    TDisplayContext,
} from './DisplayContext';
import {
    UserPreferences,
    useUserPreferencesStore,
} from '../../store/userPreferencesStore.ts';
import {Layout} from '../AssetList/Layouts';
import {StateSetter} from '../../types.ts';
import createStateSetterProxy from '@alchemy/react-hooks/src/createStateSetterProxy.ts';

type Props = PropsWithChildren<{
    defaultState?: Partial<DisplayPreferences>;
    inOverflowDiv?: TDisplayContext['inOverflowDiv'];
    displayPrefKey?: keyof UserPreferences & ('display' | 'displayBatchEdit');
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
        playVideos: true,
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
        if (displayPref) {
            setState(prev => ({
                ...prev,
                ...(displayPref ?? {}),
                previewOptions: {
                    ...prev.previewOptions,
                    ...(displayPref ?? {}).previewOptions,
                },
            }));
        }
    }, [displayPref]);

    const setStateProxy = useMemo<StateSetter<DisplayPreferences>>(
        () => handler =>
            setState(
                createStateSetterProxy(handler, newState => {
                    updatePreference(displayPrefKey, newState);

                    return newState;
                })
            ),
        [updatePreference, setState]
    );

    return (
        <DisplayContext.Provider
            value={{
                inOverflowDiv,
                state,
                setState: setStateProxy,
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
