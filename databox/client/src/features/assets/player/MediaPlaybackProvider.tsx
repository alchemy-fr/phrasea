'use client';

import {
    createContext,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

/** What the player of an asset view gives to the side panel */
export type MediaPlaybackControl = {
    /** Moves to the time (seconds) and plays */
    playFrom: (time: number) => void;
    currentTime: () => number;
    /** Calls the listener on every time change; returns the unsubscribe */
    subscribe: (listener: () => void) => () => void;
};

type MediaPlaybackContextValue = {
    register: (control: MediaPlaybackControl) => () => void;
    /** A player is registered: cues can be links */
    available: boolean;
    playFrom: (time: number) => void;
    subscribeTime: (listener: () => void) => () => void;
    /** Undefined while no player is registered */
    currentTime: () => number | undefined;
};

const MediaPlaybackContext = createContext<
    MediaPlaybackContextValue | undefined
>(undefined);

/**
 * Links the player of an asset view to what the side panel shows of its
 * timeline (the cues of a transcript): one player at a time, the last one
 * registered. The time is read and followed through stable functions, so
 * that playing re-renders only what follows it. A player leaves when it
 * unmounts (another asset shown): the provider keeps nothing else.
 */
export function MediaPlaybackProvider({children}: {children: ReactNode}) {
    const controlRef = useRef<MediaPlaybackControl | undefined>(undefined);
    const [available, setAvailable] = useState(false);
    const listeners = useRef(new Set<() => void>());
    // The subscription of the listeners to the current player
    const unsubscribeControl = useRef<(() => void) | undefined>(undefined);

    const notify = useCallback(() => {
        listeners.current.forEach(l => l());
    }, []);

    const attach = useCallback(
        (control: MediaPlaybackControl | undefined) => {
            unsubscribeControl.current?.();
            unsubscribeControl.current = undefined;
            controlRef.current = control;
            setAvailable(!!control);
            if (control && listeners.current.size > 0) {
                unsubscribeControl.current = control.subscribe(notify);
            }
            notify();
        },
        [notify]
    );

    const register = useCallback(
        (control: MediaPlaybackControl) => {
            attach(control);

            return () => {
                if (controlRef.current === control) {
                    attach(undefined);
                }
            };
        },
        [attach]
    );

    useEffect(
        () => () => {
            unsubscribeControl.current?.();
            unsubscribeControl.current = undefined;
        },
        []
    );

    const subscribeTime = useCallback(
        (listener: () => void) => {
            listeners.current.add(listener);
            if (controlRef.current && !unsubscribeControl.current) {
                unsubscribeControl.current =
                    controlRef.current.subscribe(notify);
            }

            return () => {
                listeners.current.delete(listener);
                if (listeners.current.size === 0) {
                    unsubscribeControl.current?.();
                    unsubscribeControl.current = undefined;
                }
            };
        },
        [notify]
    );

    const playFrom = useCallback((time: number) => {
        controlRef.current?.playFrom(time);
    }, []);

    const currentTime = useCallback(
        () => controlRef.current?.currentTime(),
        []
    );

    const value = useMemo<MediaPlaybackContextValue>(
        () => ({register, available, playFrom, subscribeTime, currentTime}),
        [register, available, playFrom, subscribeTime, currentTime]
    );

    return (
        <MediaPlaybackContext.Provider value={value}>
            {children}
        </MediaPlaybackContext.Provider>
    );
}

/** Undefined outside of an asset view */
export function useMediaPlayback(): MediaPlaybackContextValue | undefined {
    return useContext(MediaPlaybackContext);
}

/**
 * Makes the player the one of the asset view while the control is defined
 * (and the component mounted). The control must be stable.
 */
export function useRegisterMediaPlayback(
    control: MediaPlaybackControl | undefined
): void {
    const register = useMediaPlayback()?.register;

    useEffect(() => {
        if (!register || !control) {
            return;
        }

        return register(control);
    }, [register, control]);
}
