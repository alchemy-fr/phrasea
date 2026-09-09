import {useCallback, useState} from 'react';
import {toast} from 'sonner';

export type LatLng = {lat: number; lng: number};

export function useBrowserLocation() {
    const [loading, setLoading] = useState(false);

    const requestLocation = useCallback((): Promise<LatLng | undefined> => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            toast.error('Geolocation is not available in this browser');

            return Promise.resolve(undefined);
        }
        setLoading(true);

        return new Promise(resolve => {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    setLoading(false);
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                    });
                },
                err => {
                    setLoading(false);
                    toast.error(err.message);
                    resolve(undefined);
                },
                {timeout: 10_000, maximumAge: 60_000}
            );
        });
    }, []);

    return {requestLocation, loading};
}
