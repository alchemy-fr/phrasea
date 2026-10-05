import {PropsWithChildren, useMemo, useState} from 'react';
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
import {Layout} from '../AssetList/Layouts/layout';
import {StateSetter} from '../../types.ts';
import {mergeDeep} from '../../lib/merge';

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

    const state = useMemo<DisplayPreferences>(
        () =>
            mergeDeep({
                layout: Layout.Grid,
                thumbSize: 200,
                displayPreview: true,
                displayAttributes: true,
                playVideos: true,
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
            }),
        [displayPref]
    );

    const setState = useMemo<StateSetter<DisplayPreferences>>(
        () => handler => {
            const newState =
                typeof handler === 'function' ? handler(state) : handler;
            updatePreference(displayPrefKey, newState);
        },
        [updatePreference, state]
    );

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
