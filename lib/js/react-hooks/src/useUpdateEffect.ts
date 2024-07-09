import {useEffect, useRef} from 'react';
import {propsAreSame} from "./utils";
export default function useUpdateEffect(effect: () => any, trackingValues: any[] = []) {
    const trackingRef = useRef(trackingValues);

    useEffect(() => {
        console.log('trackingRef.current, trackingValues', trackingRef.current, trackingValues, propsAreSame(trackingRef.current, trackingValues));
        if (!propsAreSame(trackingRef.current, trackingValues)) {
            trackingRef.current = trackingValues;

            return effect();
        }
    }, trackingValues);
}
