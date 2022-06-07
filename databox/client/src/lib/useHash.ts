import {useCallback, useEffect, useState} from "react";

const useHash = (): [string, (newHash: string) => boolean] => {
    const [hash, setHash] = useState<string>(() => window.location.hash);

    const hashChangeHandler = useCallback(() => {
        setHash(window.location.hash);
    }, []);

    useEffect(() => {
        const e = 'hashchange';
        window.addEventListener(e, hashChangeHandler);

        return () => {
            window.removeEventListener(e, hashChangeHandler);
        };
        // eslint-disable-next-line
    }, []);

    const updateHash = useCallback((newHash: string): boolean => {
        if (newHash !== hash?.substring(1)) {
            window.location.hash = newHash;
            return true;
        }

        return false;
    }, [hash]);

    return [hash, updateHash];
};

export default useHash;

