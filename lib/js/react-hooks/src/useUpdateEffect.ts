import {useEffect, useRef} from 'react';
import {propsAreSame} from './utils';
export default function useUpdateEffect(
    effect: () => any,
    trackingValues: any[] = []
) {
    const trackingRef = useRef(trackingValues);

    useEffect(() => {
        if (!propsAreSame(trackingRef.current, trackingValues)) {
            trackingRef.current = trackingValues;

            return effect();
        }
    }, trackingValues);
}
