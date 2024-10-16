import {useEffect, useState} from 'react';

const defaultSettings: PositionOptions = {
    enableHighAccuracy: false,
    timeout: Infinity,
    maximumAge: 0,
};

type Position = {
    timestamp: EpochTimeStamp;
} & Omit<GeolocationCoordinates, 'toJSON'>;

export const useBrowserPosition = (
    enabled: boolean,
    watch = false,
    userSettings: PositionOptions = {}
) => {
    const settings: PositionOptions = {
        ...defaultSettings,
        ...userSettings,
    };

    const [position, setPosition] = useState<Position>();
    const [error, setError] = useState<string | undefined>();

    const onChange = ({coords, timestamp}: GeolocationPosition) => {
        setPosition({
            accuracy: coords.accuracy,
            altitude: coords.altitude,
            altitudeAccuracy: coords.altitudeAccuracy,
            heading: coords.heading,
            latitude: coords.latitude,
            longitude: coords.longitude,
            speed: coords.speed,
            timestamp,
        });
    };

    const onError: PositionErrorCallback = error => {
        setError(error.message);
    };

    useEffect(() => {
        if (!enabled) {
            return;
        }
        if (!navigator || !navigator.geolocation) {
            setError('Geolocation is not supported');
            return;
        }

        if (watch) {
            const watcher = navigator.geolocation.watchPosition(
                onChange,
                onError,
                settings
            );
            return () => navigator.geolocation.clearWatch(watcher);
        }

        navigator.geolocation.getCurrentPosition(onChange, onError, settings);
    }, [
        enabled,
        settings.enableHighAccuracy,
        settings.timeout,
        settings.maximumAge,
    ]);

    return {position, error};
};
