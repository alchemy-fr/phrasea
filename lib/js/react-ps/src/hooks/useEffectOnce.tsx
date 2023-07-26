import {useEffect, useRef} from "react";

function propsAreSame(a: any[], b: any[]): boolean {
    for (let i in a) {
        if (b[i] !== a[i]) {
            return false;
        }
    }

    return true;
}

export default function useEffectOnce(
    handler: () => void,
    trackingValues: any[],
) {
    const runRef = useRef<boolean>(false);
    const trackingRef = useRef(trackingValues);

    useEffect(() => {
        if (!runRef.current || !propsAreSame(trackingRef.current, trackingValues)) {
            runRef.current = true;
            trackingRef.current = trackingValues;

            handler();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, trackingValues);
}
