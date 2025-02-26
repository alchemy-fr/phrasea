import {useEffect, useRef} from 'react';
import {propsAreSame} from './utils';

export default function useEffectOnce(
    handler: () => any,
    trackingValues: any[]
) {
    const runRef = useRef<boolean>(false);
    const trackingRef = useRef(trackingValues);

    useEffect(() => {
        if (
            !runRef.current ||
            !propsAreSame(trackingRef.current, trackingValues)
        ) {
            runRef.current = true;
            trackingRef.current = trackingValues;

            return handler();
        }
    }, trackingValues);
}
