import {PropsWithChildren, useEffect, useState} from 'react';
import {
    DisplayContext,
    DisplayPreferences,
    PlayingContext,
} from './DisplayContext';
import {toast} from 'react-toastify';

import {useTranslation} from 'react-i18next';
import {useUserPreferencesStore} from '../../store/userPreferencesStore.ts';

type Props = PropsWithChildren<{
    defaultState?: Partial<DisplayPreferences>;
}>;

export default function DisplayProvider({children, defaultState = {}}: Props) {
    const [playingContext, setPlayingContext] = useState<PlayingContext>();
    const displayPref = useUserPreferencesStore(s => s.preferences)?.display;

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
                    state.previewLocked
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

                setState(p => ({
                    ...p,
                    previewLocked: !p.previewLocked,
                }));
            }
        };

        window.addEventListener('keydown', handler);

        return () => {
            window.removeEventListener('keydown', handler);
        };
    }, [state.previewLocked]);

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
