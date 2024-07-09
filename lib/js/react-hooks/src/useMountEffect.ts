import {useEffect, useRef} from 'react';
export default function useMountEffect(effect: () => any, dependencies: any[] = []) {
    const isInitialMount = useRef(true);

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;

            return effect();
        }
    }, dependencies);
}
